<?php

namespace Database\Seeders;

use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use RuntimeException;

class AnnualLeaveTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveType = LeaveType::query()->where('code', 'ANNUAL')->first();

        if ($leaveType === null) {
            throw new RuntimeException('Cannot seed the annual leave template because the ANNUAL leave type does not exist.');
        }

        $templateCode = <<<'HTML'
<div style="font-family: 'Times New Roman', serif; font-size: 15px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 20px;">
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
      <td style="width: 25%; vertical-align: bottom; padding: 0;">
        <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
      </td>
      <td style="width: 75%; vertical-align: bottom; text-align: right; border-bottom: 2px solid #7AC142; padding: 0 0 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
      </td>
    </tr>
  </table>

  <h2 style="margin: 0 0 16px; text-align: center; font-family: Arial, sans-serif; font-size: 17px; font-weight: bold; text-decoration: underline;">LEAVE REQUEST AND HANDOVER FORM</h2>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 6px;"><strong>NAME:</strong> <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span></td>
      <td style="width: 50%; border: 1px solid #222; padding: 6px;"><strong>DEPARTMENT:</strong> <span style="color: #16a34a;">{{department}}</span></td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px; line-height: 1.7;">
        <strong>Reason for requested leave:</strong><br />
        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-family: &quot;DejaVu Sans&quot;, sans-serif; font-size: 12px;">&#10003;</span> Annual Leave<br />
        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">&nbsp;</span> Sick Leave<br />
        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">&nbsp;</span> Maternity Leave<br />
        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">&nbsp;</span> Paternity Leave<br />
        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">&nbsp;</span> Other ______________________________
      </td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 8px;">
        <strong>Dates Requested:</strong>&nbsp; From: <span style="color: red;">{{start_date}}</span>&nbsp;&nbsp; To: <span style="color: red;">{{end_date}}</span><br /><br />
        <strong>No. of days requested:</strong> <span style="color: red;">{{total_days}}</span>
      </td>
    </tr>
  </table>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 7px; vertical-align: top;">
        <strong>Employee Signature:</strong><br />
        <div style="min-height: 55px;"><span style="color: #16a34a;">{{employee_signature}}</span></div>
        <strong>Date:</strong> <span style="color: #16a34a;">{{issue_date}}</span>
      </td>
      <td style="width: 50%; border: 1px solid #222; padding: 7px; vertical-align: top;">
        <strong>Employee Code:</strong> <span style="color: #16a34a;">{{employee_code}}</span><br /><br />
        <strong>Local Contact Number:</strong> <span style="color: red;">{{local_contact_no}}</span>
      </td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px;">
        <strong>Travelling Outside Country:</strong>&nbsp;
        <span style="color: red;">{{travel_yes_checkbox}}</span> Yes&nbsp;&nbsp;
        <span style="color: red;">{{travel_no_checkbox}}</span> No
      </td>
    </tr>
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 7px;"><strong>Destination of Vacation:</strong> <span style="color: red;">{{destination}}</span></td>
      <td style="width: 50%; border: 1px solid #222; padding: 7px;"><strong>Contact No:</strong> <span style="color: red;">{{travel_contact_no}}</span></td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px;">
        <strong>Ticket:</strong>&nbsp;
        <span style="color: red;">{{ticket_checkbox}}</span> Ticket&nbsp;&nbsp;
        <span style="color: red;">{{no_ticket_checkbox}}</span> No Ticket
        <span style="font-size: 11px;">(*To be marked by Boss)</span>
      </td>
    </tr>
  </table>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr><td colspan="3" style="border: 1px solid #222; padding: 6px;"><strong>Approval of leave:</strong></td></tr>
    <tr>
      <td style="width: 33.33%; height: 75px; border: 1px solid #222; padding: 6px;"><strong>Department Head</strong></td>
      <td style="width: 33.33%; height: 75px; border: 1px solid #222; padding: 6px;"><strong>Finance Manager</strong></td>
      <td style="width: 33.33%; height: 75px; border: 1px solid #222; padding: 6px;"><strong>Director</strong></td>
    </tr>
    <tr><td colspan="3" style="height: 45px; border: 1px solid #222; padding: 6px;"><strong>Notes / Comments:</strong></td></tr>
  </table>

  <p style="margin: 4px 0 0; font-weight: bold; text-decoration: underline;">Declaration:</p>
  <p style="margin: 0; font-size: 13px; line-height: 1.3;">
    I hereby declare that I will remain available for any emergency queries as required by the company and can be reached through any designated contact method. I will also promptly inform the company in the event of any personal emergency that may affect my availability.
    <strong>Signature:</strong>
  </p>
  <div style="page-break-inside: avoid; margin: 0; padding: 0;">
    <div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>
    <div style="margin: 0; padding: 0; line-height: 1;">______________________________</div>
  </div>

  <div style="page-break-before: always;"></div>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
      <td style="width: 25%; vertical-align: bottom; padding: 0;">
        <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
      </td>
      <td style="width: 75%; vertical-align: bottom; text-align: right; border-bottom: 2px solid #7AC142; padding: 0 0 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
      </td>
    </tr>
  </table>

  <h2 style="margin: 0 0 12px; font-family: Arial, sans-serif; font-size: 14px; text-decoration: underline;">HANDOVER DETAILS</h2>
  <p style="margin: 0 0 8px;"><strong>Duties Handed Over to:</strong> <span style="color: red;">{{handover_person}}</span> ______________________________</p>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
    <thead>
      <tr>
        <th style="width: 8%; border: 1px solid #222; padding: 6px; text-align: left;">Sl No</th>
        <th style="border: 1px solid #222; padding: 6px; text-align: left;">Task / Responsibility</th>
      </tr>
    </thead>
    <tbody>{{handover_description}}</tbody>
  </table>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
    <tr><td style="height: 90px; border: 1px solid #222; padding: 8px; vertical-align: top;"><strong>Other Remarks:</strong><br /><br /><span style="color: red;">{{remarks}}</span></td></tr>
  </table>
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 8px; vertical-align: top; height: 100px;">
        <strong>Signature of Person Handing Over:</strong><br />
        <div style="min-height: 55px;"><span style="color: red;">{{handover_giver_signature}}</span></div>
      </td>
      <td style="width: 50%; border: 1px solid #222; padding: 8px; vertical-align: top; height: 100px;">
        <strong>Signature of Person Receiving:</strong><br />
        <div style="min-height: 35px;">1. <span style="color: red;">{{handover_receiver_signature}}</span></div>
        <div>2. ____________________</div>
        <div>3. ____________________</div>
      </td>
    </tr>
  </table>
</div>
HTML;

        LeaveTemplate::withTrashed()->firstOrCreate(
            [
                'leave_type_id' => $leaveType->id,
                'organisation_id' => null,
                'template_name' => 'Annual Leave and Handover Form 2026',
            ],
            [
                'template_code' => $templateCode,
                'status' => 1,
            ],
        );
    }
}
