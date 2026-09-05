<?php
/**
 * Weekly program PDF, built with FPDF (includes/vendor/fpdf) in the app's
 * own black/gold branding. FPDF's core fonts only support Windows-1252, so
 * all text is transliterated through pdf_txt() before being drawn — plain
 * ASCII labels only, no emoji (they'd just drop silently).
 */
require_once __DIR__ . '/vendor/fpdf/fpdf.php';
require_once __DIR__ . '/functions.php';

function pdf_txt(?string $s): string
{
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s ?? '');
    return $converted !== false ? $converted : (string) $s;
}

class WeeklyProgramPDF extends FPDF
{
    public string $rangeLabel = '';
    /** @var array{name:string,minister_name:string,minister_photo:?string} */
    public array $ministry = ['name' => '', 'minister_name' => '', 'minister_photo' => null];

    private const BLACK = [16, 17, 18];
    private const GOLD  = [244, 194, 13];
    private const MUTED = [107, 112, 118];
    private const LINE  = [228, 230, 234];
    private const PANEL = [248, 248, 249];

    public function Header(): void
    {
        $this->SetFillColor(...self::BLACK);
        $this->Rect(0, 0, 210, 26, 'F');

        // Photo on the left (matching print_weekly.php's layout), text to its right.
        $photoD = 20;
        $photoCx = 12 + $photoD / 2;
        $photoCy = 13;
        $photoPath = $this->ministry['minister_photo'] ? dirname(__DIR__) . '/' . $this->ministry['minister_photo'] : '';
        $this->CircularPhoto($photoPath, $photoCx, $photoCy, $photoD, initials_from_name($this->ministry['minister_name'] ?: $this->ministry['name']));

        $textX = 12 + $photoD + 6;
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetXY($textX, 6);
        $this->Cell(0, 7, pdf_txt($this->ministry['name']));
        $this->SetFont('Helvetica', '', 10);
        $this->SetXY($textX, 14);
        $this->Cell(0, 6, pdf_txt(APP_NAME . ' — ' . $this->ministry['minister_name']));

        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, 26, 210, 2, 'F');

        $this->SetTextColor(...self::BLACK);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetXY(12, 33);
        $this->Cell(0, 8, pdf_txt('WEEKLY PROGRAM — ' . $this->rangeLabel), 0, 1, 'C');
        $this->SetY(44);
    }

    /**
     * Draw $file cropped to a circle (a "cover" fit, biased slightly toward
     * the top like a head-and-shoulders portrait) with a gold ring, matching
     * the circular photo used on the site's print views. Falls back to a
     * plain gold initials circle if the file is missing or unreadable — FPDF
     * has no GD dependency to lean on here, unlike the browser's CSS crop.
     */
    private function CircularPhoto(string $file, float $cx, float $cy, float $d, string $fallbackInitials = '?'): void
    {
        $r = $d / 2;
        $info = ($file !== '' && is_file($file)) ? @getimagesize($file) : false;

        $this->_out('q');
        $this->_out($this->circlePath($cx, $cy, $r));
        $this->_out('W n');

        if ($info) {
            [$srcW, $srcH] = $info;
            $scale = max($d / $srcW, $d / $srcH);
            $drawW = $srcW * $scale;
            $drawH = $srcH * $scale;
            $offsetX = $cx - $drawW / 2;
            $offsetY = $cy - $r - ($drawH - $d) * 0.25; // keep more of the top in frame
            $this->Image($file, $offsetX, $offsetY, $drawW, $drawH);
        } else {
            $this->SetFillColor(...self::GOLD);
            $this->Rect($cx - $r, $cy - $r, $d, $d, 'F');
            $this->SetTextColor(...self::BLACK);
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetXY($cx - $r, $cy - 3);
            $this->Cell($d, 6, pdf_txt($fallbackInitials), 0, 0, 'C');
        }
        $this->_out('Q');

        $this->_out('q');
        $this->SetDrawColor(...self::GOLD);
        $this->SetLineWidth(0.8);
        $this->_out($this->circlePath($cx, $cy, $r));
        $this->_out('S');
        $this->_out('Q');
    }

    /** PDF path-construction commands tracing a circle, via 4 cubic-bezier quarter-arcs. */
    private function circlePath(float $cx, float $cy, float $r): string
    {
        $k = $this->k;
        $h = $this->h;
        $lx = 4 / 3 * (M_SQRT2 - 1) * $r;
        $py = fn($y) => ($h - $y) * $k;

        $cmd  = sprintf("%.2F %.2F m\n", ($cx + $r) * $k, $py($cy));
        $cmd .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", ($cx + $r) * $k, $py($cy - $lx), ($cx + $lx) * $k, $py($cy - $r), $cx * $k, $py($cy - $r));
        $cmd .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", ($cx - $lx) * $k, $py($cy - $r), ($cx - $r) * $k, $py($cy - $lx), ($cx - $r) * $k, $py($cy));
        $cmd .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", ($cx - $r) * $k, $py($cy + $lx), ($cx - $lx) * $k, $py($cy + $r), $cx * $k, $py($cy + $r));
        $cmd .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", ($cx + $lx) * $k, $py($cy + $r), ($cx + $r) * $k, $py($cy + $lx), ($cx + $r) * $k, $py($cy));
        return $cmd;
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetDrawColor(...self::LINE);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(0, 8, pdf_txt('Page ' . $this->PageNo() . ' · Generated ' . date('jS F Y, g:i A') . ' · ' . APP_NAME), 0, 0, 'C');
    }

    /** Start a new day section, breaking to a fresh page first if it (plus a little breathing room) won't fit. */
    public function DayHeader(string $label): void
    {
        if ($this->GetY() + 14 > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $this->SetFillColor(...self::BLACK);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->Cell(0, 8, '  ' . pdf_txt($label), 0, 1, 'L', true);
        $this->SetTextColor(...self::BLACK);
        $this->Ln(1.5);
    }

    public function NoMeetings(string $label): void
    {
        $this->SetFont('Helvetica', 'I', 9);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(0, 7, '  ' . pdf_txt($label), 0, 1);
        $this->SetTextColor(...self::BLACK);
        $this->Ln(2);
    }

    /** One meeting/trip block: a light panel with a gold left rule. */
    public function MeetingBlock(array $m, string $ymd): void
    {
        $isTrip = $m['event_type'] === 'trip';
        $badge  = trip_day_badge($m, $ymd);
        $team   = array_column(meeting_team((int) $m['id']), 'name');

        $lines = [];
        $lines[] = ['label' => null, 'text' => $m['title'] . ($isTrip
            ? ' (' . fmt_date_range($m['meeting_date'], $m['end_date'] ?: $m['meeting_date']) . ')'
            : ''), 'bold' => true];
        if ($isTrip) {
            $tripTime = ($m['start_time'] || $m['end_time'])
                ? (($m['start_time'] ? fmt_time($m['start_time']) : '?') . ' - ' . ($m['end_time'] ? fmt_time($m['end_time']) : '?'))
                : 'Trip';
            $lines[] = ['label' => 'Time', 'text' => $tripTime . ($badge ? " ({$badge})" : '')];
        } else {
            $lines[] = ['label' => 'Time', 'text' => fmt_time($m['start_time']) . ' - ' . fmt_time($m['end_time'])];
        }
        $lines[] = ['label' => 'Venue', 'text' => $m['venue']];
        if ($m['agenda'])            $lines[] = ['label' => 'Agenda', 'text' => $m['agenda']];
        if ($m['attendees'])         $lines[] = ['label' => 'Attendees', 'text' => $m['attendees']];
        if ($team)                   $lines[] = ['label' => 'Team', 'text' => join_names($team)];
        if (!empty($m['contact']))   $lines[] = ['label' => 'Contact', 'text' => $m['contact']];
        if (!empty($m['notes']))     $lines[] = ['label' => 'Notes', 'text' => $m['notes']];

        // Measure the block height first (using the same font each line will
        // actually render in) so it can start a fresh page as a whole.
        $lineHeight = 5.2;
        $height = 4;
        foreach ($lines as $l) {
            $text = $l['label'] ? "{$l['label']}: {$l['text']}" : $l['text'];
            $this->SetFont('Helvetica', $l['label'] ? '' : 'B', $l['label'] ? 9 : 10.5);
            $height += $this->countWrappedLines(pdf_txt($text), 176) * $lineHeight;
        }
        $height += 3;

        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        $x = $this->GetX();
        $yTop = $this->GetY();

        $this->SetFillColor(...self::PANEL);
        $this->Rect($x, $yTop, 186, $height, 'F');
        $this->SetFillColor(...self::GOLD);
        $this->Rect($x, $yTop, 1.2, $height, 'F');

        $this->SetLeftMargin($x + 5);
        $this->SetXY($x + 5, $yTop + 3);
        foreach ($lines as $l) {
            $this->SetX($x + 5);
            if ($l['label']) {
                $this->SetFont('Helvetica', 'B', 9);
                $this->Write($lineHeight, pdf_txt($l['label'] . ': '));
                $this->SetFont('Helvetica', '', 9);
                $this->Write($lineHeight, pdf_txt($l['text']));
                $this->Ln($lineHeight);
            } else {
                $this->SetFont('Helvetica', 'B', 10.5);
                $this->MultiCell(176, $lineHeight, pdf_txt($l['text']));
            }
        }
        $this->SetLeftMargin($x);
        $this->SetXY($x, $yTop + $height + 3);
    }

    /** How many wrapped lines MultiCell would use for $txt at $w mm width. */
    private function countWrappedLines(string $txt, float $w): int
    {
        $cw = &$this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c === ' ') $sep = $i;
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) { if ($i === $j) $i++; } else { $i = $sep + 1; }
                $nl++; $j = $i; $l = 0; $sep = -1;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

/** Build the current (or any) Mon-Fri week's program PDF and return raw PDF bytes. */
function build_weekly_program_pdf(string $monday, string $friday, int $ministryId): string
{
    $weekMeetings = meetings_between($monday, $friday, $ministryId);

    $pdf = new WeeklyProgramPDF();
    $pdf->ministry = ministry_by_id($ministryId) ?? $pdf->ministry;
    $pdf->rangeLabel = strtoupper((new DateTime($monday))->format('jS F') . ' to ' . (new DateTime($friday))->format('jS F Y'));
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();

    $cursor = new DateTime($monday);
    for ($i = 0; $i < 5; $i++) {
        $ymd = $cursor->format('Y-m-d');
        $dayMeetings = array_values(array_filter($weekMeetings, fn($m) => meeting_covers_day($m, $ymd)));

        $pdf->DayHeader(strtoupper($cursor->format('l, jS F Y')));

        if (!$dayMeetings) {
            $pdf->NoMeetings(empty_day_label($ymd));
        } else {
            foreach ($dayMeetings as $m) {
                $pdf->MeetingBlock($m, $ymd);
            }
        }
        $cursor->modify('+1 day');
    }

    return $pdf->Output('S');
}
