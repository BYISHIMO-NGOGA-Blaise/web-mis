<?php
/**
 * PDF Generator for school reports.
 * Generates valid PDF 1.4 files using Helvetica built-in fonts.
 */
class PdfGenerator {

    private $pages = array();
    private $currentPage = -1;
    private $y = 0;
    private $pageWidth = 595.28;
    private $pageHeight = 841.89;
    private $marginL = 20;
    private $marginR = 20;
    private $marginT = 20;
    private $marginB = 25;
    private $headerCb = null;
    private $footerCb = null;

    private $fontLabelCounter = 0;
    private $fontStyles = array();
    private $currentFontRef = 'F1';
    private $currentFontSize = 10;
    private $textR = 0; private $textG = 0; private $textB = 0;
    private $fillR = 0; private $fillG = 0; private $fillB = 0;
    private $drawR = 0; private $drawG = 0; private $drawB = 0;

    public function __construct() {
        $this->addPage();
    }

    public function setHeaderCallback(callable $cb) { $this->headerCb = $cb; }
    public function setFooterCallback(callable $cb) { $this->footerCb = $cb; }
    public function getY() { return $this->y; }
    public function setY($y) { $this->y = $y; }
    public function getX() { return $this->marginL; }
    public function getPageWidth() { return $this->pageWidth; }
    public function getPageHeight() { return $this->pageHeight; }
    public function getMarginLeft() { return $this->marginL; }
    public function getMarginRight() { return $this->marginR; }
    public function getContentWidth() { return $this->pageWidth - $this->marginL - $this->marginR; }
    public function getPageNumber() { return count($this->pages); }

    private function stream($code) {
        if ($this->currentPage < 0) return;
        $this->pages[$this->currentPage]['stream'] .= $code;
    }

    public function addPage() {
        if ($this->currentPage >= 0 && $this->footerCb) {
            call_user_func($this->footerCb, $this);
        }
        $this->pages[] = array('stream' => '');
        $this->currentPage = count($this->pages) - 1;
        $this->y = $this->marginT;
        if ($this->headerCb) {
            call_user_func($this->headerCb, $this);
        }
    }

    public function checkPageBreak($needed) {
        if ($this->y + $needed > $this->pageHeight - $this->marginB) {
            $this->addPage();
            return true;
        }
        return false;
    }

    private function registerFont($name, $style) {
        $base = 'Helvetica';
        if ($style === 'bold') $base = 'Helvetica-Bold';
        elseif ($style === 'italic') $base = 'Helvetica-Oblique';
        elseif ($style === 'bolditalic') $base = 'Helvetica-BoldOblique';

        $key = $base;
        if (!isset($this->fontStyles[$key])) {
            $this->fontLabelCounter++;
            $this->fontStyles[$key] = 'F' . $this->fontLabelCounter;
        }
        return $this->fontStyles[$key];
    }

    public function setFont($name = 'helvetica', $style = '', $size = 10) {
        $this->currentFontRef = $this->registerFont($name, $style);
        $this->currentFontSize = $size;
    }

    public function setTextColor($r, $g, $b) { $this->textR = $r; $this->textG = $g; $this->textB = $b; }
    public function setFillColor($r, $g, $b) { $this->fillR = $r; $this->fillG = $g; $this->fillB = $b; }
    public function setDrawColor($r, $g, $b) { $this->drawR = $r; $this->drawG = $g; $this->drawB = $b; }

    private function c($v) { return sprintf('%.3f', $v / 255); }

    public function text($x, $y, $str) {
        $safe = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $str);
        $pdfY = $this->pageHeight - $y;
        $this->stream("BT\n");
        $this->stream($this->c($this->textR) . ' ' . $this->c($this->textG) . ' ' . $this->c($this->textB) . " rg\n");
        $this->stream("1 0 0 1 {$x} {$pdfY} Tm\n");
        $this->stream("/{$this->currentFontRef} {$this->currentFontSize} Tf\n");
        $this->stream("({$safe}) Tj\n");
        $this->stream("ET\n");
    }

    public function line($x1, $y1, $x2, $y2) {
        $this->stream($this->c($this->drawR) . ' ' . $this->c($this->drawG) . ' ' . $this->c($this->drawB) . " RG\n");
        $this->stream(sprintf("%.2f %.2f m %.2f %.2f l S\n", $x1, $this->pageHeight - $y1, $x2, $this->pageHeight - $y2));
    }

    public function rect($x, $y, $w, $h, $style = 'S') {
        $this->stream($this->c($this->fillR) . ' ' . $this->c($this->fillG) . ' ' . $this->c($this->fillB) . " rg\n");
        $this->stream($this->c($this->drawR) . ' ' . $this->c($this->drawG) . ' ' . $this->c($this->drawB) . " RG\n");
        $this->stream(sprintf("%.2f %.2f %.2f %.2f re %s\n", $x, $this->pageHeight - $y, $w, $h, $style));
    }

    public function heading($text, $size = 16) {
        $this->checkPageBreak($size + 10);
        $this->setFont('helvetica', 'bold', $size);
        $this->setTextColor(15, 23, 42);
        $this->text($this->marginL, $this->y, $text);
        $this->y += $size + 6;
    }

    public function subheading($text, $size = 12) {
        $this->checkPageBreak($size + 8);
        $this->setFont('helvetica', 'bold', $size);
        $this->setTextColor(79, 70, 229);
        $this->text($this->marginL, $this->y, $text);
        $this->y += $size + 4;
    }

    public function bodyText($text, $size = 10) {
        $this->setFont('helvetica', '', $size);
        $this->setTextColor(51, 65, 85);
        $maxW = $this->getContentWidth();
        $words = explode(' ', $text);
        $line = '';
        foreach ($words as $word) {
            $test = $line ? "{$line} {$word}" : $word;
            if (strlen($test) * ($size * 0.5) > $maxW && $line) {
                $this->text($this->marginL, $this->y, $line);
                $this->y += $size + 2;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        if ($line) {
            $this->text($this->marginL, $this->y, $line);
            $this->y += $size + 2;
        }
    }

    public function infoRow($label, $value, $size = 10) {
        $this->setFont('helvetica', 'bold', $size);
        $this->setTextColor(71, 85, 105);
        $this->text($this->marginL, $this->y, $label);
        $this->setFont('helvetica', '', $size);
        $this->setTextColor(15, 23, 42);
        $this->text($this->marginL + 60, $this->y, (string)$value);
        $this->y += $size + 3;
    }

    public function spacer($h = 5) { $this->y += $h; }

    public function drawTable($headers, $rows, $colWidths, $opts = array()) {
        $x0 = $this->marginL;
        $rowH = isset($opts['rowHeight']) ? $opts['rowHeight'] : 7;
        $hBg = isset($opts['headerBg']) ? $opts['headerBg'] : array(79, 70, 229);
        $hFg = isset($opts['headerFg']) ? $opts['headerFg'] : array(255, 255, 255);
        $altBg = isset($opts['altRowBg']) ? $opts['altRowBg'] : array(241, 245, 249);
        $fSize = isset($opts['fontSize']) ? $opts['fontSize'] : 8;
        $hSize = isset($opts['headerFontSize']) ? $opts['headerFontSize'] : 8;
        $totalW = array_sum($colWidths);

        $this->checkPageBreak($rowH + 8);
        $this->setFillColor($hBg[0], $hBg[1], $hBg[2]);
        $this->rect($x0, $this->y, $totalW, $rowH + 3, 'F');
        $this->setFont('helvetica', 'bold', $hSize);
        $this->setTextColor($hFg[0], $hFg[1], $hFg[2]);
        $cx = $x0;
        foreach ($headers as $i => $h) {
            $this->text($cx + 2, $this->y + 5, $h);
            $cx += $colWidths[$i];
        }
        $this->y += $rowH + 5;

        foreach ($rows as $ri => $row) {
            $this->checkPageBreak($rowH + 4);
            if ($ri % 2 == 1) {
                $this->setFillColor($altBg[0], $altBg[1], $altBg[2]);
                $this->rect($x0, $this->y, $totalW, $rowH + 1, 'F');
            }
            $this->setFont('helvetica', '', $fSize);
            $this->setTextColor(15, 23, 42);
            $cx = $x0;
            foreach ($row as $i => $cell) {
                $txt = (string)($cell === null ? '-' : $cell);
                $maxChars = (int)($colWidths[$i] / ($fSize * 0.45));
                if (strlen($txt) > $maxChars) {
                    $txt = substr($txt, 0, max($maxChars - 2, 4)) . '..';
                }
                $this->text($cx + 2, $this->y + 4.5, $txt);
                $cx += $colWidths[$i];
            }
            $this->y += $rowH + 1;

            $this->setFillColor(226, 232, 240);
            $this->rect($x0, $this->y, $totalW, 0.25, 'F');
            $this->y += 1;
        }

        $this->setFillColor($hBg[0], $hBg[1], $hBg[2]);
        $this->rect($x0, $this->y, $totalW, 0.8, 'F');
        $this->y += 4;
    }

    public function output($filename = 'report.pdf') {
        if ($this->footerCb) {
            call_user_func($this->footerCb, $this);
        }

        $objNum = 0;
        $objEntries = array();

        // Font objects
        foreach ($this->fontStyles as $baseFont => $label) {
            $objNum++;
            $objEntries[$objNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$baseFont} >>";
        }

        // Content stream objects
        $pageContentObjIds = array();
        foreach ($this->pages as $page) {
            $objNum++;
            $pageContentObjIds[] = $objNum;
            $stream = $page['stream'];
            $objEntries[$objNum] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }

        // Page objects
        $pageObjIds = array();
        foreach ($pageContentObjIds as $cid) {
            $objNum++;
            $pageObjIds[] = $objNum;

            $fontRes = '';
            foreach ($this->fontStyles as $baseFont => $fontLabel) {
                $num = intval(substr($fontLabel, 1));
                $fontRes .= "/{$fontLabel} {$num} 0 R ";
            }
            $objEntries[$objNum] = "<< /Type /Page /Parent 0 R /Contents {$cid} 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << {$fontRes}>> >>";
        }

        // Pages catalog
        $objNum++;
        $pagesId = $objNum;
        $kids = '';
        foreach ($pageObjIds as $pid) {
            $kids .= "{$pid} 0 R ";
        }
        $objEntries[$objNum] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($this->pages) . " >>";

        // Root catalog
        $objNum++;
        $rootId = $objNum;
        $objEntries[$objNum] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";

        // Replace /Parent 0 R with actual pages ID
        foreach ($objEntries as $k => $v) {
            $objEntries[$k] = str_replace('/Parent 0 R', "/Parent {$pagesId} 0 R", $v);
        }

        // Build PDF
        $pdf = "%PDF-1.4\n";
        $offsets = array();

        for ($n = 1; $n <= $objNum; $n++) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$objEntries[$n]}\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $totalObjs = $objNum + 1;

        $xref = "xref\n0 {$totalObjs}\n";
        $xref .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $objNum; $n++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }

        $trailer = "trailer\n<< /Size {$totalObjs} /Root {$rootId} 0 R >>\n";
        $trailer .= "startxref\n{$xrefStart}\n%%EOF";

        $pdf .= $xref . $trailer;

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $pdf;
        exit();
    }
}
