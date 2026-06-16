<?php

declare(strict_types=1);

namespace App\Onboarding;

abstract class BaseOnboardingFlow
{
    abstract protected function stepOne(): void;

    abstract protected function stepTwo(): void;

    abstract protected function stepThree(): void;

    public function run(): void
    {
        $this->stepOne();
        $this->stepTwo();
        $this->stepThree();
    }
}
