<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class TextArrayType extends AbstractType
{
    public function getParent()
    {
        return HiddenType::class;
    }

    public function getBlockPrefix() {
        return 'text_array';
    }
}
