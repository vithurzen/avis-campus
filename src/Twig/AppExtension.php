<?php

namespace App\Twig;

use App\Repository\ReportRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private ReportRepository $reportRepository) {}

    public function getGlobals(): array
    {
        return [
            'openCount' => $this->reportRepository->countOpen(),
        ];
    }
}
