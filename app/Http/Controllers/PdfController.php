<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class PdfController extends Controller
{
    /**
     * Render one of the print pages (`to`, a path starting with /print/ or a playbook packet) to a PDF download.
     */
    public function download(Request $request, PdfRenderer $renderer): Response
    {
        $to = $this->printPath($request->query('to'));

        $signed = URL::temporarySignedRoute('pdf.session', now()->addMinutes(2), [
            'user' => $request->user()->getKey(),
            'to' => $to,
        ], absolute: false);

        try {
            $pdf = $renderer->render($signed);
        } catch (RuntimeException $e) {
            return response($e->getMessage(), 503, ['Content-Type' => 'text/plain']);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str(config('app.name'))->slug().'-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    /**
     * Entry point for the headless browser: a short-lived signed link that signs it in as the
     * requesting coach and forwards it to the print page.
     */
    public function session(Request $request, User $user): RedirectResponse
    {
        // The link signs a user in, so it works exactly once.
        abort_unless(Cache::add('pdf-session:'.$request->query('signature'), true, now()->addMinutes(5)), 403);

        Auth::login($user);

        return redirect($this->printPath($request->query('to')));
    }

    private function printPath(mixed $path): string
    {
        $path = is_string($path) ? $path : '';

        abort_unless(
            (str_starts_with($path, '/print/') || preg_match('#^/playbooks/\d+/packet$#', strtok($path, '?') ?: '')) && ! str_contains($path, '//'),
            422,
            'Only print pages can be exported.',
        );

        return $path;
    }
}
