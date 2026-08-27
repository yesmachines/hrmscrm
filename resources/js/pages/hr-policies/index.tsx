import { Head, Link, useForm } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Eye,
    FileText,
    Plus,
    Shield,
    UploadCloud,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';

type PolicyItem = {
    id: number;
    policy_name: string;
    document_code: string;
    version: string;
    remarks: string | null;
    updated_at: string;
    file_url: string | null;
    files: {
        id: number;
        version_no: string;
        file_url: string;
        uploaded_date: string;
        change_notes: string | null;
    }[];
};

type PolicyType = {
    id: number;
    document_name: string;
    document_code: string;
};

type Props = {
    policies: PolicyItem[];
    policyTypes: PolicyType[];
};

export default function HrPoliciesIndex({ policies, policyTypes }: Props) {
    const [uploadOpen, setUploadOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        document_type_id: '',
        policy_name: '',
        version: '1.0',
        remarks: '',
        file: null as File | null,
    });

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        post('/hr-policies', {
            onSuccess: () => {
                reset();
                setUploadOpen(false);
            },
        });
    };

    const getPolicyColor = (code: string) => {
        if (code.includes('leave')) {
            return {
                bg: 'bg-emerald-50 text-emerald-600 border-emerald-100',
                btn: 'bg-emerald-600 hover:bg-emerald-700',
            };
        }
        if (code.includes('wfh')) {
            return {
                bg: 'bg-amber-50 text-amber-600 border-amber-100',
                btn: 'bg-amber-600 hover:bg-amber-700',
            };
        }
        return {
            bg: 'bg-blue-50 text-blue-600 border-blue-100',
            btn: 'bg-blue-600 hover:bg-blue-700',
        };
    };

    return (
        <>
            <Head title="Company HR Policies" />

            <div className="mx-auto flex w-full max-w-full 2xl:max-w-[1600px] flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <Heading
                        title="Company HR Policies"
                        description="Official HR, Leave, and Workplace Policies with Version Control"
                    />

                    <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
                        <DialogTrigger asChild>
                            <Button className="bg-primary hover:bg-primary/90 text-white gap-1.5 shadow-sm">
                                <Plus className="size-4" />
                                Upload New Policy Version
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <Shield className="size-5 text-primary" />
                                    Upload Policy Document
                                </DialogTitle>
                                <DialogDescription>
                                    Publish a new company-wide policy document or revision.
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={handleUpload} className="space-y-4 py-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="document_type_id">Policy Type *</Label>
                                    <Select
                                        value={data.document_type_id}
                                        onValueChange={(val) => {
                                            const type = policyTypes.find((t) => t.id.toString() === val);
                                            setData((prev) => ({
                                                ...prev,
                                                document_type_id: val,
                                                policy_name: type ? `${type.document_name} ${new Date().getFullYear()}` : '',
                                            }));
                                        }}
                                    >
                                        <SelectTrigger id="document_type_id">
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {policyTypes.map((t) => (
                                                <SelectItem key={t.id} value={t.id.toString()}>
                                                    {t.document_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.document_type_id} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="policy_name">Policy Name *</Label>
                                    <Input
                                        id="policy_name"
                                        value={data.policy_name}
                                        onChange={(e) => setData('policy_name', e.target.value)}
                                        placeholder="e.g. HR Policy 2026"
                                    />
                                    <InputError message={errors.policy_name} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="version">Version Number *</Label>
                                    <Input
                                        id="version"
                                        value={data.version}
                                        onChange={(e) => setData('version', e.target.value)}
                                        placeholder="e.g. 1.0 or 1.1"
                                    />
                                    <InputError message={errors.version} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="remarks">Revision Notes / Remarks</Label>
                                    <textarea
                                        id="remarks"
                                        rows={2}
                                        value={data.remarks}
                                        onChange={(e) => setData('remarks', e.target.value)}
                                        placeholder="Summary of policy updates..."
                                        className="w-full rounded-lg border border-input bg-background p-2.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                    <InputError message={errors.remarks} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="file">Policy PDF File *</Label>
                                    <Input
                                        id="file"
                                        type="file"
                                        accept=".pdf"
                                        onChange={(e) => {
                                            if (e.target.files && e.target.files[0]) {
                                                setData('file', e.target.files[0]);
                                            }
                                        }}
                                    />
                                    <InputError message={errors.file} />
                                </div>

                                <DialogFooter className="pt-3">
                                    <Button variant="outline" type="button" onClick={() => setUploadOpen(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing} className="bg-primary hover:bg-primary/90 text-white">
                                        {processing ? 'Publishing...' : 'Publish Policy'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Policies Cards Grid (Matching Figma iPhone 16 Plus - 11) */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {policies.length === 0 ? (
                        <div className="col-span-full rounded-2xl border border-dashed border-border bg-white p-12 text-center text-muted-foreground">
                            <FileText className="size-12 mx-auto text-muted-foreground/40 mb-3" />
                            <p className="text-base font-semibold">No Policy Documents Yet</p>
                            <p className="text-sm mt-1">Click "Upload New Policy Version" to publish company policies.</p>
                        </div>
                    ) : (
                        policies.map((policy) => {
                            const colors = getPolicyColor(policy.document_code);

                            return (
                                <div
                                    key={policy.id}
                                    className="rounded-3xl border border-border bg-white p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow"
                                >
                                    <div className="space-y-4">
                                        <div className="flex items-start gap-4">
                                            <div className={`size-14 rounded-2xl flex items-center justify-center border ${colors.bg}`}>
                                                <FileText className="size-7" />
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold text-foreground">{policy.policy_name}</h3>
                                                <div className="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
                                                    <span className="font-semibold text-foreground">{policy.version}</span>
                                                    <span>•</span>
                                                    <span>Updated: {policy.updated_at}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {policy.remarks && (
                                            <p className="text-xs text-muted-foreground bg-neutral-50 p-2.5 rounded-xl border border-border/50">
                                                {policy.remarks}
                                            </p>
                                        )}
                                    </div>

                                    <div className="mt-6 pt-4 border-t border-border/60">
                                        {policy.file_url ? (
                                            <Button asChild className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl h-11 shadow-sm gap-2">
                                                <a href={policy.file_url} target="_blank" rel="noopener noreferrer">
                                                    <Eye className="size-4" />
                                                    VIEW DOCUMENT
                                                </a>
                                            </Button>
                                        ) : (
                                            <Button disabled variant="outline" className="w-full rounded-xl h-11 font-semibold">
                                                No PDF Attached
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </>
    );
}

HrPoliciesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'HR Policies',
            href: '/hr-policies',
        },
    ],
};
