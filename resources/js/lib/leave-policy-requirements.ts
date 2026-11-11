interface LeavePolicyRequirements {
    requires_attachment: boolean;
    requires_handover: boolean;
    requires_document_after_days?: number | null;
}

export function getLeavePolicyRequirements(
    policy: LeavePolicyRequirements | null | undefined,
    totalDays: number,
): { requiresAttachment: boolean; requiresHandover: boolean } {
    const threshold = policy?.requires_document_after_days;
    const durationApplies = threshold == null || totalDays > threshold;

    return {
        requiresAttachment: Boolean(policy?.requires_attachment) && durationApplies,
        requiresHandover: Boolean(policy?.requires_handover) && durationApplies,
    };
}
