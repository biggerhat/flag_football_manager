<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CopyToTeamController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrillController;
use App\Http\Controllers\FamilyPageController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\GameDayController;
use App\Http\Controllers\GamePlanController;
use App\Http\Controllers\OpponentController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PlaybookController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\RestoreController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SharedPlaybookController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamExportController;
use App\Http\Controllers\TeamImportController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamOverviewController;
use App\Http\Controllers\TrashController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return config('coach.auto_login')
        ? to_route('dashboard')
        : Inertia::render('Welcome', ['inviteOnly' => config('coach.registration') !== 'open' && User::query()->exists()]);
})->name('home');

Route::get('pdf/session/{user}', [PdfController::class, 'session'])->middleware('signed:relative')->name('pdf.session');

Route::get('calendar/feed/{token}.ics', [CalendarFeedController::class, 'show'])->middleware('throttle:60,1')->name('calendar.feed');
Route::get('family/{token}', FamilyPageController::class)->middleware('throttle:60,1')->name('family');
Route::get('share/{token}', [SharedPlaybookController::class, 'show'])->middleware('throttle:60,1')->name('share.show');
Route::get('invitations/{token}', [TeamInvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::post('seasons/switch', [SeasonController::class, 'switch'])->name('seasons.switch');
    Route::get('opponents', [OpponentController::class, 'index'])->name('opponents');
    Route::get('stats', StatsController::class)->name('stats');
    Route::get('plays/{play}/versions', [PlayController::class, 'versions'])->name('plays.versions');
    Route::get('calendar', CalendarController::class)->name('calendar');
    Route::post('calendar/feed', [CalendarFeedController::class, 'store'])->name('calendar.feed.store');
    Route::post('invitations/{token}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('team', [TeamController::class, 'show'])->name('team.show');
    Route::get('teams', TeamOverviewController::class)->name('teams.index');
    Route::get('club', [ClubController::class, 'show'])->name('club.show');
    Route::post('club', [ClubController::class, 'store'])->name('club.store');
    Route::delete('club', [ClubController::class, 'destroy'])->name('club.destroy');
    Route::post('club/teams', [ClubController::class, 'addTeam'])->name('club.teams.add');
    Route::delete('club/teams/{team}', [ClubController::class, 'removeTeam'])->name('club.teams.remove');
    Route::post('club/members', [ClubController::class, 'inviteMember'])->name('club.members.invite');
    Route::put('club/members/{member}', [ClubController::class, 'updateMember'])->name('club.members.update');
    Route::delete('club/members/{member}', [ClubController::class, 'removeMember'])->name('club.members.remove');
    Route::delete('club/invitations/{invitation}', [ClubController::class, 'cancelInvitation'])->name('club.invitations.cancel');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('copy/{kind}/{id}', CopyToTeamController::class)->whereNumber('id')->name('copy');
    Route::post('teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
    Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::put('teams/{team}/announcement', [TeamController::class, 'announce'])->name('teams.announce');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');
    Route::post('team/invitations', [TeamMemberController::class, 'invite'])->name('team.invite');
    Route::delete('team/invitations/{invitation}', [TeamMemberController::class, 'cancelInvitation'])->name('team.invitations.cancel');
    Route::put('team/members/{member}', [TeamMemberController::class, 'update'])->name('team.members.update');
    Route::delete('team/members/{member}', [TeamMemberController::class, 'destroy'])->name('team.members.destroy');
});

Route::middleware(['auth', 'verified', 'team.edit'])->group(function () {
    Route::post('seasons', [SeasonController::class, 'store'])->name('seasons.store');
    Route::delete('seasons/{season}', [SeasonController::class, 'destroy'])->whereNumber('season')->name('seasons.destroy');
    Route::put('opponents', [OpponentController::class, 'update'])->name('opponents.update');
    Route::get('trash', TrashController::class)->name('trash');
    Route::get('team/backup', TeamExportController::class)->name('team.backup.export');
    Route::post('team/backup', TeamImportController::class)->name('team.backup.import');
    Route::post('restore/{kind}/{id}', RestoreController::class)->whereNumber('id')->name('restore');

    Route::post('plays/{play}/versions/{version}/restore', [PlayController::class, 'restoreVersion'])->whereNumber('version')->name('plays.versions.restore');
    Route::post('plays/{play}/favorite', [PlayController::class, 'favorite'])->name('plays.favorite');
    Route::post('plays/{play}/duplicate', [PlayController::class, 'duplicate'])->name('plays.duplicate');
    Route::post('plays/bulk', [PlayController::class, 'bulk'])->name('plays.bulk');
    Route::post('plays/move', [PlayController::class, 'move'])->name('plays.move');
    Route::post('plays/{play}/variation', [PlayController::class, 'variation'])->name('plays.variation');
    Route::resource('plays', PlayController::class)->except('index', 'show');
    Route::get('playbooks/{playbook}/packet', [PlaybookController::class, 'packet'])->name('playbooks.packet');
    Route::post('playbooks/{playbook}/share', [PlaybookController::class, 'share'])->name('playbooks.share');
    Route::resource('playbooks', PlaybookController::class)->except('create', 'edit');

    Route::resource('players', PlayerController::class)->only('index', 'store', 'update', 'destroy');

    Route::get('gameplans/{gameplan}/play', [GameDayController::class, 'show'])->name('gameplans.gameday');
    Route::put('gameplans/{gameplan}/score', [GameDayController::class, 'score'])->name('gameplans.score');
    Route::post('gameplans/{gameplan}/calls', [GameDayController::class, 'store'])->name('gameplans.calls.store');
    Route::delete('gameplans/{gameplan}/calls/{call}', [GameDayController::class, 'destroy'])->name('gameplans.calls.destroy');
    Route::resource('gameplans', GamePlanController::class)->except('show');

    Route::post('formations/{formation}/duplicate', [FormationController::class, 'duplicate'])->name('formations.duplicate');
    Route::resource('formations', FormationController::class)->except('show');

    Route::resource('drills', DrillController::class)->except('show');

    Route::post('practices/{practice}/duplicate', [PracticeController::class, 'duplicate'])->name('practices.duplicate');
    Route::get('practices/{practice}/run', [PracticeController::class, 'run'])->name('practices.run');
    Route::resource('practices', PracticeController::class)->except('show');

    Route::get('print/plays', [PrintController::class, 'plays'])->name('print.plays');
    Route::get('pdf', [PdfController::class, 'download'])->name('pdf');

    Route::get('print/formations', [PrintController::class, 'formations'])->name('print.formations');
    Route::get('print/practices/{practice}', [PrintController::class, 'practice'])->name('print.practice');
});

require __DIR__.'/settings.php';
