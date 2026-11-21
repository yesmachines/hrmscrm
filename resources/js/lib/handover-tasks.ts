export function parseHandoverTasks(
    description: string | null | undefined,
): string[] {
    if (!description?.trim()) {
        return [''];
    }

    try {
        const tasks: unknown = JSON.parse(description);

        if (
            Array.isArray(tasks) &&
            tasks.every((task) => typeof task === 'string')
        ) {
            return tasks;
        }
    } catch (error) {
        if (!(error instanceof SyntaxError)) {
            throw error;
        }
    }

    return [description];
}

export function serializeHandoverTasks(tasks: readonly string[]): string {
    return JSON.stringify(tasks);
}

export function normalizeHandoverDescription(
    description: string | null | undefined,
): string {
    const tasks = parseHandoverTasks(description)
        .map((task) => task.trim())
        .filter((task) => task !== '');

    return tasks.length > 0 ? JSON.stringify(tasks) : '';
}

export function hasHandoverTasks(
    description: string | null | undefined,
): boolean {
    return parseHandoverTasks(description).some((task) => task.trim() !== '');
}
