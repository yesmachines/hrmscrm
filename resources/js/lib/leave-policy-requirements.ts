interface LeavePolicyRequirements {
    requires_attachment: boolean;
    requires_handover: boolean;
    requires_document_after_days?: number | null;
    requires_weekend_document?: boolean;
}

function dateRangeContainsWeekend(
    startDate?: string | null,
    endDate?: string | null,
): boolean {
    const normalizedStartDate = startDate?.slice(0, 10);
    const normalizedEndDate = endDate?.slice(0, 10);

    if (
        !normalizedStartDate ||
        !normalizedEndDate ||
        !/^\d{4}-\d{2}-\d{2}$/.test(normalizedStartDate) ||
        !/^\d{4}-\d{2}-\d{2}$/.test(normalizedEndDate) ||
        normalizedEndDate < normalizedStartDate
    ) {
        return false;
    }

    const currentDate = new Date(`${normalizedStartDate}T12:00:00`);
    const lastDate = new Date(`${normalizedEndDate}T12:00:00`);

    if (
        Number.isNaN(currentDate.getTime()) ||
        Number.isNaN(lastDate.getTime())
    ) {
        return false;
    }

    while (currentDate <= lastDate) {
        const dayOfWeek = currentDate.getDay();

        if (dayOfWeek === 0 || dayOfWeek === 6) {
            return true;
        }

        currentDate.setDate(currentDate.getDate() + 1);
    }

    return false;
}

export function getLeavePolicyRequirements(
    policy: LeavePolicyRequirements | null | undefined,
    totalDays: number,
    startDate?: string | null,
    endDate?: string | null,
): { requiresAttachment: boolean; requiresHandover: boolean } {
    const threshold = policy?.requires_document_after_days;
    const durationApplies = threshold == null || totalDays >= threshold;
    const requiresWeekendAttachment =
        Boolean(policy?.requires_weekend_document) &&
        dateRangeContainsWeekend(startDate, endDate);

    return {
        requiresAttachment:
            (Boolean(policy?.requires_attachment) && durationApplies) ||
            requiresWeekendAttachment,
        requiresHandover: Boolean(policy?.requires_handover) && durationApplies,
    };
}
