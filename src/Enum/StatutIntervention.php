<?php

namespace App\Enum;

enum StatutIntervention: string
{
    case EnCours = 'En cours';
    case Terminee = 'Terminée';
    case Annulee = 'Annulee';
}
