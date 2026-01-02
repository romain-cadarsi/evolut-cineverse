<?php
namespace App\CustomPageModel;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;

interface OutputStyleAwareInterface
{
    public function setOutput(OutputStyle $output): void;
}
