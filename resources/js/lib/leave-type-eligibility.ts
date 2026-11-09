type LeaveTypeEligibility = {
    gender?: string | null;
    religion?: string | null;
};

function normalizeGender(gender: string | null | undefined): string | null {
    switch (gender?.trim().toLowerCase()) {
        case 'm':
        case 'male':
            return 'male';
        case 'f':
        case 'female':
            return 'female';
        default:
            return null;
    }
}

function normalizeReligion(religion: string | null | undefined): string | null {
    const normalized = religion?.trim().toLowerCase();

    if (!normalized) {
        return null;
    }

    switch (normalized) {
        case 'hindu':
            return 'hinduism';
        case 'muslim':
        case 'islamic':
            return 'islam';
        case 'christian':
            return 'christianity';
        case 'sikh':
            return 'sikhism';
        case 'buddhist':
            return 'buddhism';
        case 'jain':
            return 'jainism';
        case 'jewish':
            return 'judaism';
        default:
            return normalized;
    }
}

function religionsMatch(employeeReligion: string | null | undefined, requiredReligion: string): boolean {
    const normalizedEmployeeReligion = normalizeReligion(employeeReligion);
    const normalizedRequiredReligion = normalizeReligion(requiredReligion);

    return normalizedEmployeeReligion === normalizedRequiredReligion
        || (normalizedEmployeeReligion !== null
            && normalizedRequiredReligion !== null
            && (normalizedEmployeeReligion.includes(normalizedRequiredReligion)
                || normalizedRequiredReligion.includes(normalizedEmployeeReligion)));
}

export function filterEligibleLeaveTypes<T extends LeaveTypeEligibility>(
    leaveTypes: T[],
    employeeGender: string | null | undefined,
    employeeReligion: string | null | undefined,
): T[] {
    const normalizedEmployeeGender = normalizeGender(employeeGender);

    return leaveTypes.filter((leaveType) => {
        const requiredGender = normalizeGender(leaveType.gender);

        if (requiredGender !== null && normalizedEmployeeGender !== requiredGender) {
            return false;
        }

        const requiredReligion = normalizeReligion(leaveType.religion);

        return requiredReligion === null || religionsMatch(employeeReligion, requiredReligion);
    });
}
