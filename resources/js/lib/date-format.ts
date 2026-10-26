export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const isoDate = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (isoDate) {
        return `${isoDate[3]}-${isoDate[2]}-${isoDate[1]}`;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date).replaceAll('/', '-');
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date).replaceAll('/', '-');
}
