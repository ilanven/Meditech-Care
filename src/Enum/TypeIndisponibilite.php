<?php

namespace App\Enum;

enum TypeIndisponibilite: string
{
    case Conge = 'Congé';
    case Maladie = 'Maladie';
    case Flemme = 'Trop la flemme';
    case Autre = 'Autre';
}
