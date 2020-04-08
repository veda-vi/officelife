<?php

namespace App\Interfaces;

interface ServiceInterface
{
    public function rules();
    public function logs();
    public function execute(array $data);
}
