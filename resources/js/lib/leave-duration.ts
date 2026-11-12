export function countLeaveWeekdays(startDate: string, endDate: string): number {
    const normalizedStartDate = startDate.slice(0, 10);
    const normalizedEndDate = endDate.slice(0, 10);

    if (
        !/^\d{4}-\d{2}-\d{2}$/.test(normalizedStartDate) ||
        !/^\d{4}-\d{2}-\d{2}$/.test(normalizedEndDate) ||
        normalizedEndDate < normalizedStartDate
    ) {
        return 0;
    }

    const start = new Date(`${normalizedStartDate}T00:00:00Z`);
    const end = new Date(`${normalizedEndDate}T00:00:00Z`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return 0;
    }

    let weekdays = 0;

    for (let day = start; day <= end; day = new Date(day.getTime() + 24 * 60 * 60 * 1000)) {
        const dayOfWeek = day.getUTCDay();

        if (dayOfWeek !== 0 && dayOfWeek !== 6) {
            weekdays++;
        }
    }

    return weekdays;
}
