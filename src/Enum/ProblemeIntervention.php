<?php

namespace App\Enum;

enum ProblemeIntervention: string
{
    case PanneElectrique = 'Panne électrique';
    case PanneGenerale = 'Panne générale';
    case Autre = 'Autre';
}
