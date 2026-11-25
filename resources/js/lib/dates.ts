/** "Last run Nov 3", or "Never run" when a drill or play has not been in a practice yet. */
export function lastRunLabel(date: string | null | undefined): string {
    if (!date) {
        return 'Never run';
    }

    const [year, month, day] = date.split('-').map(Number);

    return `Last run ${new Date(year, month - 1, day).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
}
