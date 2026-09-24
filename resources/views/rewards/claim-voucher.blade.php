<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Claim Voucher - {{ $reward->claim_no }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }
        body {
            background-color: #f8fafc;
            color: #0f172a;
            padding: 30px 15px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .doc-title {
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 700;
            color: #0284c7;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .claim-badge {
            text-align: right;
        }
        .claim-number {
            font-size: 20px;
            font-weight: 800;
            color: #0369a1;
            font-family: monospace;
        }
        .status-pill {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 9999px;
            letter-spacing: 0.5px;
        }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-approved { background: #e0f2fe; color: #0369a1; }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .info-label {
            color: #64748b;
        }
        .info-value {
            font-weight: 600;
            color: #1e293b;
            text-align: right;
        }

        .highlight-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .amount-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .amount-num {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
        }

        .desc-box {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 14px;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
            margin-top: 10px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 30px;
        }
        .history-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-align: left;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
        }
        .history-table td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 40px;
            padding-top: 20px;
        }
        .sig-box {
            text-align: center;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 45px;
            padding-top: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
        }

        .no-print {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .btn {
            background: #0284c7;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn:hover { background: #0369a1; }
        .btn-outline {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .btn-outline:hover { background: #f8fafc; }

        @media print {
            body { padding: 0; background: #fff; }
            .container { border: none; box-shadow: none; padding: 20px 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <button class="btn btn-outline" onclick="window.close()">Close Window</button>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="company-title">Corporate HRMS & Recognition</div>
                <div class="doc-title">Official Reward & Recognition Claim Form</div>
            </div>
            <div class="claim-badge">
                <div class="claim-number">{{ $reward->claim_no }}</div>
                <div class="status-pill status-{{ $reward->status }}">{{ $reward->status }}</div>
            </div>
        </div>

        <!-- Employee & Category Info -->
        <div class="grid-2">
            <div>
                <div class="section-title">Claimant Information</div>
                <div class="info-row">
                    <span class="info-label">Employee Name:</span>
                    <span class="info-value">{{ $reward->employee?->user?->name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee ID:</span>
                    <span class="info-value">{{ $reward->employee?->emp_num ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Designation:</span>
                    <span class="info-value">{{ $reward->employee?->designation ?? 'N/A' }}</span>
                </div>
            </div>

            <div>
                <div class="section-title">Claim Metadata</div>
                <div class="info-row">
                    <span class="info-label">Reward Category:</span>
                    <span class="info-value">{{ $reward->category?->reward_name ?? 'N/A' }} ({{ $reward->category?->short_code }})</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Submission Date:</span>
                    <span class="info-value">{{ $reward->submitted_date ? $reward->submitted_date->format('d M Y, h:i A') : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Updated:</span>
                    <span class="info-value">{{ $reward->updated_at->format('d M Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Amount & Description -->
        <div class="highlight-box">
            <div class="amount-display">
                <div>
                    <div class="section-title" style="margin-bottom: 0;">Claimed Award Amount</div>
                    <div style="font-size: 11px; color: #64748b;">Disbursement upon final HR / Finance authorization</div>
                </div>
                <div class="amount-num">${{ number_format((float) $reward->amount, 2) }}</div>
            </div>

            <div style="margin-top: 15px;">
                <div class="section-title">Achievement Justification & Description</div>
                <div class="desc-box">
                    {{ $reward->description }}
                </div>
            </div>
        </div>

        <!-- Approval History / Audit Trail -->
        <div class="section-title">Workflow Approval & Verification History</div>
        <table class="history-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Action / Status</th>
                    <th style="width: 25%;">Authorized By</th>
                    <th style="width: 25%;">Timestamp</th>
                    <th style="width: 25%;">Reviewer Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reward->approvalStatuses as $history)
                    <tr>
                        <td>
                            <strong style="text-transform: capitalize; color: #0284c7;">{{ $history->status }}</strong>
                        </td>
                        <td>{{ $history->doneByUser?->name ?? 'System' }}</td>
                        <td>{{ $history->done_on ? $history->done_on->format('d M Y, h:i A') : 'N/A' }}</td>
                        <td>{{ $history->comments ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #94a3b8;">No approval logs recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Signatures Block -->
        <div class="signatures">
            <div class="sig-box">
                <div class="sig-line">Employee / Claimant Signature</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">HR Department Approval</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">Finance Disbursement Authority</div>
            </div>
        </div>
    </div>

</body>
</html>
