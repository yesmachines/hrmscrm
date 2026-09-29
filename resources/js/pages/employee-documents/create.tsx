import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, FileDown, FileUp, Info, UploadCloud } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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

type DocumentType = {
    id: number;
    category_id: number;
    document_name: string;
    document_code: string;
    requires_number: boolean;
    requires_expiry: boolean;
    requires_hr_approval?: boolean;
    has_blank_form?: boolean;
    blank_form_url?: string | null;
};

type Category = {
    id: number;
    category_name: string;
    short_code: string;
    document_types: DocumentType[];
};

type Employee = {
    id: number;
    user_id: number;
    emp_num: string;
    designation: string;
    user?: { name: string };
};

type DownloadableForm = {
    id: number;
    name: string;
    code: string;
    download_url: string;
};

type Props = {
    categories: Category[];
    employees: Employee[];
    downloadableForms?: DownloadableForm[];
};

export default function EmployeeDocumentCreate({ categories, employees, downloadableForms = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        category_id: '',
        document_type_id: '',
        document_title: '',
        document_number: '',
        issue_date: '',
        expiry_date: '',
        remarks: '',
        status: 'submitted',
        file: null as File | null,
    });

    const activeCategory = categories.find((c) => c.id.toString() === data.category_id);
    const availableTypes = activeCategory ? activeCategory.document_types : [];
    const selectedType = availableTypes.find((t) => t.id.toString() === data.document_type_id);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/employee-documents');
    };

    return (
        <>
            <Head title="Direct Document Upload" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-3">
                    <Button asChild variant="outline" size="icon" className="size-9 rounded-xl">
                        <Link href="/employee-documents">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Direct HR Document Upload"
                        description="Upload official employment, personal, performance, or disciplinary documents for an employee"
                    />
                </div>

                {downloadableForms && downloadableForms.length > 0 && (
                    <div className="rounded-2xl border border-sky-200/80 bg-linear-to-r from-sky-50/70 to-blue-50/40 p-4 sm:p-5 shadow-xs">
                        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-semibold text-sky-950 flex items-center gap-2">
                                    <FileDown className="size-4 text-sky-600" />
                                    Download Blank Claim & Declaration Forms
                                </h3>
                                <p className="text-xs text-sky-800/80 mt-1">
                                    Need to submit a claim or nomination? Download the official blank PDF, fill & sign it manually, then upload below for HR approval.
                                </p>
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {downloadableForms.map((form) => (
                                <Button
                                    key={form.id}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="border-sky-300/80 bg-white hover:bg-sky-100 text-sky-900 text-xs shadow-2xs font-medium"
                                    onClick={() => window.open(form.download_url, '_blank')}
                                >
                                    <Download className="size-3.5 mr-1.5 text-sky-600" />
                                    {form.name}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="rounded-2xl border border-border bg-white p-6 shadow-sm space-y-5">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            {/* Employee Selection */}
                            <div className="space-y-2">
                                <Label htmlFor="employee_id">Target Employee *</Label>
                                <Select
                                    value={data.employee_id}
                                    onValueChange={(val) => setData('employee_id', val)}
                                >
                                    <SelectTrigger id="employee_id">
                                        <SelectValue placeholder="Select employee" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((emp) => (
                                            <SelectItem key={emp.id} value={emp.id.toString()}>
                                                {emp.user?.name || `Employee #${emp.id}`} ({emp.emp_num || 'No Code'} - {emp.designation})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.employee_id} />
                            </div>

                            {/* Category Selection */}
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Document Category *</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(val) => {
                                        setData((prev) => ({
                                            ...prev,
                                            category_id: val,
                                            document_type_id: '',
                                        }));
                                    }}
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Select category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((cat) => (
                                            <SelectItem key={cat.id} value={cat.id.toString()}>
                                                {cat.category_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>

                            {/* Document Type Selection */}
                            <div className="space-y-2">
                                <Label htmlFor="document_type_id">Document Type *</Label>
                                <Select
                                    value={data.document_type_id}
                                    disabled={!data.category_id}
                                    onValueChange={(val) => {
                                        const type = availableTypes.find((t) => t.id.toString() === val);
                                        setData((prev) => ({
                                            ...prev,
                                            document_type_id: val,
                                            document_title: type ? type.document_name : '',
                                            status: type?.requires_hr_approval ? 'submitted' : 'approved',
                                        }));
                                    }}
                                >
                                    <SelectTrigger id="document_type_id">
                                        <SelectValue placeholder={data.category_id ? "Select document type" : "Select category first"} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableTypes.map((type) => (
                                            <SelectItem key={type.id} value={type.id.toString()}>
                                                {type.document_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.document_type_id} />
                            </div>

                            {/* Document Title */}
                            <div className="space-y-2">
                                <Label htmlFor="document_title">Document Title *</Label>
                                <Input
                                    id="document_title"
                                    value={data.document_title}
                                    onChange={(e) => setData('document_title', e.target.value)}
                                    placeholder="e.g. Annual Appraisal 2026 / Offer Letter"
                                />
                                <InputError message={errors.document_title} />
                            </div>

                            {/* Blank Form Download Banner if available */}
                            {selectedType?.has_blank_form && (
                                <div className="sm:col-span-2 rounded-xl border border-amber-200 bg-amber-50/80 p-4 shadow-2xs">
                                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-lg bg-amber-100 p-2 text-amber-800 shrink-0">
                                                <FileDown className="size-5" />
                                            </div>
                                            <div>
                                                <h4 className="text-xs font-semibold text-amber-950 uppercase tracking-wider">
                                                    Official Blank Form Template Available
                                                </h4>
                                                <p className="text-xs text-amber-800/90 mt-0.5 max-w-xl">
                                                    Download and print this blank {selectedType.document_name} PDF. Complete and sign it manually (attach all necessary receipts/documents), then upload the scanned copy below.
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="bg-amber-600 hover:bg-amber-700 text-white font-medium shadow-xs text-xs shrink-0"
                                            onClick={() => window.open(selectedType.blank_form_url || `/document-types/${selectedType.id}/blank-form?download=1`, '_blank')}
                                        >
                                            <Download className="size-3.5 mr-1.5" />
                                            Download Blank Form (PDF)
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {/* Approval Workflow Info */}
                            {selectedType && (
                                <div className="sm:col-span-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-xl bg-neutral-50 px-4 py-2.5 text-xs text-muted-foreground border border-neutral-200">
                                    <span className="flex items-center gap-1.5">
                                        <Info className="size-3.5 text-muted-foreground" />
                                        Workflow:
                                        <strong className="text-neutral-800">
                                            {selectedType.requires_hr_approval ? 'Requires HR Review & Approval' : 'Auto-approved on upload'}
                                        </strong>
                                    </span>
                                    <span>
                                        Status after upload: <strong className="text-neutral-800 capitalize">{data.status === 'submitted' ? 'Pending Approval' : data.status}</strong>
                                    </span>
                                </div>
                            )}

                            {/* Document Number (if required / optional) */}
                            <div className="space-y-2">
                                <Label htmlFor="document_number">Document / ID Number</Label>
                                <Input
                                    id="document_number"
                                    value={data.document_number}
                                    onChange={(e) => setData('document_number', e.target.value)}
                                    placeholder="e.g. LC-88921-00 or Passport No"
                                />
                                <InputError message={errors.document_number} />
                            </div>

                            {/* Issue Date */}
                            <div className="space-y-2">
                                <Label htmlFor="issue_date">Issue Date</Label>
                                <Input
                                    id="issue_date"
                                    type="date"
                                    value={data.issue_date}
                                    onChange={(e) => setData('issue_date', e.target.value)}
                                />
                                <InputError message={errors.issue_date} />
                            </div>

                            {/* Expiry Date */}
                            <div className="space-y-2">
                                <Label htmlFor="expiry_date">Expiry Date (for renewals & alerts)</Label>
                                <Input
                                    id="expiry_date"
                                    type="date"
                                    value={data.expiry_date}
                                    onChange={(e) => setData('expiry_date', e.target.value)}
                                />
                                <InputError message={errors.expiry_date} />
                            </div>
                        </div>

                        {/* Remarks */}
                        <div className="space-y-2">
                            <Label htmlFor="remarks">Notes / Remarks</Label>
                            <textarea
                                id="remarks"
                                rows={3}
                                value={data.remarks}
                                onChange={(e) => setData('remarks', e.target.value)}
                                placeholder="Add any confidential notes or instructions..."
                                className="w-full rounded-lg border border-input bg-background p-2.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <InputError message={errors.remarks} />
                        </div>

                        {/* File Upload Zone */}
                        <div className="space-y-2">
                            <Label htmlFor="file">Upload Document File (PDF, JPG, PNG) *</Label>
                            <div className="border-2 border-dashed border-border rounded-xl p-6 text-center hover:border-primary/50 transition-colors bg-neutral-50/50">
                                <UploadCloud className="mx-auto size-10 text-muted-foreground/50 mb-2" />
                                <input
                                    id="file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) => {
                                        if (e.target.files && e.target.files[0]) {
                                            setData('file', e.target.files[0]);
                                        }
                                    }}
                                    className="hidden"
                                />
                                <label
                                    htmlFor="file"
                                    className="cursor-pointer text-sm font-semibold text-primary hover:underline"
                                >
                                    Click here to select a file from your computer
                                </label>
                                <p className="text-xs text-muted-foreground mt-1">Maximum file size: 10MB (PDF, PNG, JPG)</p>
                                {data.file && (
                                    <div className="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-semibold">
                                        <FileUp className="size-4" />
                                        {data.file.name} ({(data.file.size / 1024 / 1024).toFixed(2)} MB)
                                    </div>
                                )}
                            </div>
                            <InputError message={errors.file} />
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3">
                        <Button asChild variant="outline">
                            <Link href="/employee-documents">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-primary hover:bg-primary/90 text-white">
                            {processing ? 'Uploading...' : 'Save & Verify Document'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

EmployeeDocumentCreate.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Documents',
            href: '/employee-documents',
        },
        {
            title: 'Upload Document',
            href: '/employee-documents/create',
        },
    ],
};
