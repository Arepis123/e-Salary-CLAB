<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary Deduction Form</title>
    <style>
        @page { margin: 10mm 10mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            color: #000;
            margin: 0;
        }

        .sheet {
            border: 1.5pt solid #000;
            padding: 8pt 12pt 10pt 12pt;
            page-break-after: always;
        }
        .sheet.last { page-break-after: auto; }

        .brand { text-align: right; }
        .brand img { height: 34pt; }

        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin: 2pt 0 5pt 0;
        }

        .section-bar {
            background: #000;
            color: #fff;
            font-weight: bold;
            font-size: 9.5pt;
            padding: 1.5pt 5pt;
            margin: 0 0 5pt 0;
        }

        .note {
            font-size: 8pt;
            margin: 0 0 5pt 0;
        }

        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td {
            padding: 2.5pt 0;
            vertical-align: bottom;
            font-size: 9.5pt;
        }
        td.label { width: 140pt; }
        td.colon { width: 12pt; }

        /* Pre-filled values sit on the same ruled line the sample leaves blank. */
        .line {
            border-bottom: 0.75pt solid #000;
            display: block;
            min-height: 12pt;
            padding: 0 3pt 1pt 3pt;
        }
        .line.short { width: 200pt; }

        .tickbox {
            display: inline-block;
            border: 0.75pt solid #000;
            width: 34pt;
            /* The DejaVu check sits on a low baseline, so it reads as resting
               on the bottom border. Shorten the content box and pad beneath it
               to lift the glyph toward the centre; total height is unchanged. */
            height: 10pt;
            padding-bottom: 3pt;
            text-align: center;
            font-size: 10pt;
            line-height: 10pt;
        }

        .block { margin-bottom: 9pt; }
        .remarks-line { min-height: 13pt; }

        /* Signature lines are written on by hand, so they need real height. */
        .line.sign { min-height: 26pt; }

        /* dompdf's core Arial has no tick glyphs; DejaVu Sans (bundled) does. */
        .glyph { font-family: 'DejaVu Sans', sans-serif; }
    </style>
</head>
<body>
@foreach($forms as $form)
    <div class="sheet {{ $loop->last ? 'last' : '' }}">

        @if($logoPath)
            <div class="brand"><img src="{{ $logoPath }}" alt="CLAB"></div>
        @endif

        <div class="doc-title">SALARY DEDUCTION FORM</div>

        {{-- a) Applicant Information --}}
        <div class="section-bar">a) Applicant Information</div>
        <table class="fields block">
            <tr>
                <td class="label">Company Name</td><td class="colon">:</td>
                <td><span class="line">{{ $applicant['company_name'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Officer Name (PIC)</td><td class="colon">:</td>
                <td><span class="line">{{ $applicant['officer_name'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Telephone No. (PIC)</td><td class="colon">:</td>
                <td><span class="line">{{ $applicant['telephone'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Email (active)</td><td class="colon">:</td>
                <td><span class="line">{{ $applicant['email'] }}</span></td>
            </tr>
        </table>

        {{-- b) Deduction Information --}}
        <div class="section-bar">b) Deduction Information</div>
        <table class="fields">
            <tr>
                <td class="label">Foreign Worker's Name</td><td class="colon">:</td>
                <td><span class="line">{{ $form['worker_name'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Passport No.</td><td class="colon">:</td>
                <td><span class="line">{{ $form['worker_passport'] }}</span></td>
            </tr>
        </table>

        @php
            $tickRows = [
                ['label' => 'Advance Payment', 'ticked' => $form['has_advance_payment'], 'amount' => $form['advance_payment_amount']],
                ['label' => 'Accommodation', 'ticked' => $form['has_accommodation'], 'amount' => $form['accommodation_amount']],
                ['label' => 'No-Pay Leave (NPL)', 'ticked' => $form['has_npl'], 'amount' => $form['npl_amount']],
            ];
        @endphp

        <table class="fields">
            @foreach($tickRows as $row)
                <tr>
                    <td class="label">{{ $row['label'] }}</td><td class="colon">:</td>
                    <td style="width: 60pt;">
                        <span class="tickbox glyph">{!! $row['ticked'] ? '&#10004;' : '' !!}</span>
                    </td>
                    <td style="width: 80pt;">Amount (RM)</td>
                    <td class="colon">:</td>
                    <td>
                        <span class="line">{{ $row['ticked'] && $row['amount'] > 0 ? number_format($row['amount'], 2) : '' }}</span>
                    </td>
                </tr>
            @endforeach
        </table>

        <p class="note">(Deductions other than the above require CLAB approval)</p>

        <table class="fields block">
            <tr>
                <td class="label">Others</td><td class="colon">:</td>
                <td><span class="line">{{ $form['other_label'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Amount (RM)</td><td class="colon">:</td>
                <td><span class="line">{{ $form['other_amount'] > 0 ? number_format($form['other_amount'], 2) : '' }}</span></td>
            </tr>
        </table>

        {{-- c) Deduction Remarks --}}
        <div class="section-bar">c) Deduction Remarks</div>
        <p class="note">(Please state the reason clearly and briefly)</p>
        @php
            // The sample prints three ruled lines; keep that height whether or
            // not the entered remarks fill them.
            $remarkLines = $form['remark_lines'];
            $remarkLines[] = 'Payroll period: '.$entryPeriodName.'.';
            $remarkLines = array_pad($remarkLines, 3, '');
        @endphp
        <div class="block">
            @foreach($remarkLines as $remark)
                <div class="line remarks-line">{{ $remark }}</div>
            @endforeach
        </div>

        {{-- d) Company Acknowledgement --}}
        <div class="section-bar">d) Company Acknowledgement</div>
        <p class="note">I hereby confirm that the deduction to be made is based on the reasons stated above.</p>
        <table class="fields block">
            <tr>
                <td class="label">Officer Name</td><td class="colon">:</td>
                <td><span class="line short">{{ $applicant['officer_name'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Position</td><td class="colon">:</td>
                <td><span class="line short"></span></td>
            </tr>
            <tr><td colspan="3" style="height: 3pt;"></td></tr>
            <tr>
                <td class="label">Signature</td><td class="colon">:</td>
                <td><span class="line short sign"></span></td>
            </tr>
            <tr>
                <td class="label">Date</td><td class="colon">:</td>
                <td><span class="line short"></span></td>
            </tr>
        </table>

        {{-- d) Employee Acknowledgement --}}
        <div class="section-bar">e) Employee Acknowledgement</div>
        <table class="fields block">
            <tr>
                <td class="label">Foreign Worker's name</td><td class="colon">:</td>
                <td><span class="line short">{{ $form['worker_name'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Passport No.</td><td class="colon">:</td>
                <td><span class="line short">{{ $form['worker_passport'] }}</span></td>
            </tr>
            <tr><td colspan="3" style="height: 3pt;"></td></tr>
            <tr>
                <td class="label">Signature</td><td class="colon">:</td>
                <td><span class="line short sign"></span></td>
            </tr>
            <tr>
                <td class="label">Date</td><td class="colon">:</td>
                <td><span class="line short"></span></td>
            </tr>
        </table>

    </div>
@endforeach
</body>
</html>
