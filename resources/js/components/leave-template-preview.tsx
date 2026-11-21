import { AlertTriangle, Eye, LoaderCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    employeeId: number | string;
    leaveTypeId: number | string;
    values: Record<string, unknown>;
    templateAvailable: boolean;
};

type PreviewResponse = {
    template_name?: string;
    html?: string;
    message?: string;
};

export default function LeaveTemplatePreview({
    employeeId,
    leaveTypeId,
    values,
    templateAvailable,
}: Props) {
    const [preview, setPreview] = useState<{
        key: string;
        html: string;
        templateName: string;
    } | null>(null);
    const [previewError, setPreviewError] = useState<{
        key: string;
        message: string;
    } | null>(null);
    const [loading, setLoading] = useState(false);
    const valuesJson = useMemo(() => JSON.stringify(values), [values]);
    const previewKey = `${employeeId}:${leaveTypeId}:${valuesJson}`;
    const currentPreview = preview?.key === previewKey ? preview : null;
    const currentError =
        previewError?.key === previewKey ? previewError.message : '';

    async function previewTemplate() {
        const csrfToken = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;

        if (!csrfToken) {
            setPreviewError({
                key: previewKey,
                message:
                    'Unable to preview the template because the security token is missing.',
            });

            return;
        }

        setLoading(true);
        setPreviewError(null);

        try {
            const response = await fetch('/leave-requests/template-preview', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    employee_id: employeeId,
                    leave_type_id: leaveTypeId,
                    values,
                }),
            });
            const result = (await response.json()) as PreviewResponse;

            if (!response.ok) {
                throw new Error(
                    result.message ??
                        'The leave template preview could not be loaded.',
                );
            }

            if (typeof result.html !== 'string') {
                throw new Error(
                    'The leave template preview returned no HTML content.',
                );
            }

            setPreview({
                key: previewKey,
                html: result.html,
                templateName: result.template_name ?? 'Leave template',
            });
        } catch (previewError) {
            setPreviewError({
                key: previewKey,
                message:
                    previewError instanceof Error
                        ? previewError.message
                        : 'The leave template preview could not be loaded.',
            });
        } finally {
            setLoading(false);
        }
    }

    if (!employeeId || !leaveTypeId) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-xl border border-border bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-foreground">
                        Leave template
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Preview the selected template with the current form
                        values.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={previewTemplate}
                    disabled={!templateAvailable || loading}
                >
                    {loading ? (
                        <LoaderCircle className="mr-2 size-4 animate-spin" />
                    ) : (
                        <Eye className="mr-2 size-4" />
                    )}
                    {loading ? 'Loading preview...' : 'Preview template'}
                </Button>
            </div>

            {!templateAvailable && (
                <p
                    className="flex items-start gap-2 text-sm text-amber-800"
                    role="status"
                >
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    No active leave template is configured for this leave type
                    and organisation.
                </p>
            )}

            {currentError && (
                <p className="text-sm text-destructive" role="alert">
                    {currentError}
                </p>
            )}

            {currentPreview !== null && (
                <div className="space-y-2">
                    <p className="text-sm font-medium text-foreground">
                        {currentPreview.templateName}
                    </p>
                    <iframe
                        title={`${currentPreview.templateName} preview`}
                        sandbox=""
                        srcDoc={currentPreview.html}
                        className="h-[640px] w-full rounded-md border border-border bg-white"
                    />
                </div>
            )}
        </section>
    );
}
