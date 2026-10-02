<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /**
     * Render a QR code as an inline <svg> string (no GD extension needed).
     */
    public function svg(string $data, int $size = 220): string
    {
        $builder = new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $data,
            size: $size,
            margin: 8,
        );

        return $builder->build()->getString();
    }
}
