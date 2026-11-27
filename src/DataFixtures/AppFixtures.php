<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Appareil;
use App\Entity\Client;
use App\Entity\CreneauTravail;
use App\Entity\Diagnostic;
use App\Entity\Employe;
use App\Entity\Indisponibilite;
use App\Entity\Intervention;
use App\Entity\MaterielIntervention;
use App\Entity\MaterielStock;
use App\Entity\Operation;
use App\Enum\Poste;
use App\Enum\ProblemeIntervention;
use App\Enum\StatutIntervention;
use App\Enum\TypeIndisponibilite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    private const DEMO_PASSWORD = 'MeditechCare-demo-2026';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $clients = $this->createClients($manager);
        $employes = $this->createEmployes($manager);
        $appareils = $this->createAppareils($manager, $clients);
        $this->createMaterielStock($manager);

        $today = new \DateTimeImmutable('today');
        $this->createCreneauxTravail($manager, $employes, $today);
        $this->createIndisponibilites($manager, $employes, $today);

        $interventions = [
            'controle-ecg' => $this->createIntervention(
                $manager,
                $appareils['ecg'],
                $employes['claire'],
                ProblemeIntervention::PanneElectrique,
                StatutIntervention::Terminee,
                $today->modify('-21 days')->setTime(9, 0),
                45,
                'Contrôle des connexions et mesure de la stabilité du signal. Le câble patient présentait une usure au niveau du connecteur.',
                $today->modify('-20 days')->setTime(13, 30),
                90,
                'Le câble patient a été remplacé, puis un contrôle de sécurité électrique et un essai d’acquisition ont été réalisés. Appareil remis en service.',
            ),
            'maintenance-aspirateur' => $this->createIntervention(
                $manager,
                $appareils['aspirateur'],
                $employes['malik'],
                ProblemeIntervention::PanneGenerale,
                StatutIntervention::Terminee,
                $today->modify('-14 days')->setTime(9, 0),
                60,
                'Aspiration irrégulière et alarme de filtre. Le filtre est saturé ; le moteur fonctionne normalement lors du contrôle.',
                $today->modify('-14 days')->setTime(11, 0),
                75,
                'Filtre remplacé et circuit nettoyé. Essai d’aspiration concluant, alarmes acquittées et appareil rendu au service.',
            ),
            'reparation-monitoring' => $this->createIntervention(
                $manager,
                $appareils['monitoring'],
                $employes['lea'],
                ProblemeIntervention::Autre,
                StatutIntervention::Terminee,
                $today->modify('-10 days')->setTime(14, 0),
                50,
                'Autre : le moniteur redémarre lorsqu’il est déplacé. Faux contact identifié sur le connecteur d’alimentation.',
                $today->modify('-9 days')->setTime(8, 30),
                120,
                'Connecteur remplacé et fixation vérifiée. Le moniteur a passé un essai continu de trente minutes sans redémarrage.',
            ),
            'annulation-pompe' => $this->createIntervention(
                $manager,
                $appareils['pompe'],
                $employes['claire'],
                ProblemeIntervention::PanneElectrique,
                StatutIntervention::Annulee,
                $today->modify('-3 days')->setTime(9, 0),
                45,
                'Le diagnostic a confirmé une défaillance intermittente de l’alimentation. La maintenance a commencé avant qu’une seconde anomalie soit constatée.',
                $today->modify('-3 days')->setTime(10, 0),
                90,
                'Intervention annulée après le début de la maintenance : une pièce complémentaire doit être commandée. Les consommables déjà utilisés sont conservés dans le compte rendu.',
            ),
        ];

        $this->createConsommations($manager, $interventions);

        $manager->flush();
    }

    /**
     * @return array<string, Client>
     */
    private function createClients(ObjectManager $manager): array
    {
        $clients = [];
        $definitions = [
            'tilleuls' => [
                'Clinique des Tilleuls',
                'Cardiologie',
                '12 rue des Érables, 69003 Lyon',
                '100000019',
                'Moreau',
                'Sophie',
                'sophie.moreau@tilleuls.example.test',
                '0600000101',
            ],
            'saint-roch' => [
                'Centre médical Saint-Roch',
                'Imagerie médicale',
                '8 avenue du Parc, 33000 Bordeaux',
                '100000027',
                'Bernard',
                'Hugo',
                'hugo.bernard@saintroch.example.test',
                '0600000102',
            ],
            'rives' => [
                'Maison de santé des Rives',
                'Soins infirmiers',
                '25 quai des Saules, 44000 Nantes',
                '100000035',
                'Petit',
                'Élodie',
                'elodie.petit@rives.example.test',
                '0600000103',
            ],
            'alouettes' => [
                'Maison médicale des Alouettes',
                null,
                '4 place des Alouettes, 59000 Lille',
                '100000043',
                'Garcia',
                'Thomas',
                'thomas.garcia@alouettes.example.test',
                '0600000104',
            ],
        ];

        foreach ($definitions as $key => [$nom, $service, $adresse, $siren, $contactNom, $contactPrenom, $contactEmail, $contactTelephone]) {
            $client = (new Client())
                ->setNomEtablissement($nom)
                ->setService($service)
                ->setAdresse($adresse)
                ->setSiren($siren)
                ->setContactNom($contactNom)
                ->setContactPrenom($contactPrenom)
                ->setContactEmail($contactEmail)
                ->setContactTelephone($contactTelephone)
                ->setActif(true);

            $manager->persist($client);
            $clients[$key] = $client;
        }

        return $clients;
    }

    /**
     * @return array<string, Employe>
     */
    private function createEmployes(ObjectManager $manager): array
    {
        $definitions = [
            'administration' => ['Camille', 'Durand', 'camille.durand@meditech-care.example.test', '0600000201', Poste::Administrateur],
            'claire' => ['Claire', 'Morel', 'claire.morel@meditech-care.example.test', '0600000202', Poste::Technicien],
            'malik' => ['Malik', 'Laurent', 'malik.laurent@meditech-care.example.test', '0600000203', Poste::Technicien],
            'lea' => ['Léa', 'Roux', 'lea.roux@meditech-care.example.test', '0600000204', Poste::Technicien],
        ];
        $employes = [];

        foreach ($definitions as $key => [$prenom, $nom, $email, $telephone, $poste]) {
            $employe = (new Employe())
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setEmail($email)
                ->setTelephone($telephone)
                ->setPoste($poste)
                ->setRoles([$poste === Poste::Administrateur ? 'ROLE_ADMIN' : 'ROLE_TECHNICIEN'])
                ->setActif(true);
            $employe->setPassword($this->passwordHasher->hashPassword($employe, self::DEMO_PASSWORD));

            $manager->persist($employe);
            $employes[$key] = $employe;
        }

        return $employes;
    }

    /**
     * @param array<string, Client> $clients
     *
     * @return array<string, Appareil>
     */
    private function createAppareils(ObjectManager $manager, array $clients): array
    {
        $definitions = [
            'ecg' => ['CardioTech', 'ECG-12 Plus', '2022', 'CT-ECG-220041', $clients['tilleuls']],
            'aspirateur' => ['MediFlow', 'AspiCare 300', '2021', 'MF-AC3-210118', $clients['tilleuls']],
            'monitoring' => ['Vitalis', 'Monitor V8', '2023', 'VI-MV8-230207', $clients['saint-roch']],
            'pompe' => ['Perfusys', 'Perfusion P200', '2020', 'PS-P200-200083', $clients['rives']],
            'echographe' => ['SonoSanté', 'Écho Mobile S2', null, 'SS-EMS2-240019', $clients['saint-roch']],
        ];
        $appareils = [];

        foreach ($definitions as $key => [$marque, $modele, $annee, $numeroSerie, $client]) {
            $appareil = (new Appareil())
                ->setMarque($marque)
                ->setModele($modele)
                ->setAnnee($annee)
                ->setNumeroSerie($numeroSerie)
                ->setClient($client);

            $manager->persist($appareil);
            $appareils[$key] = $appareil;
        }

        return $appareils;
    }

    private function createMaterielStock(ObjectManager $manager): void
    {
        $definitions = [
            ['Électrodes ECG à usage unique', 120, 40],
            ['Gel conducteur médical', 18, 8],
            ['Filtre antibactérien pour aspiration', 7, 10],
            ['Gants nitrile non poudrés', 240, 60],
            ['Lingettes désinfectantes médicales', 14, 20],
            ['Connecteur d’alimentation médical', 6, 2],
            ['Joint silicone pour pompe', 0, 3],
        ];

        foreach ($definitions as [$nom, $quantite, $seuilMinimal]) {
            $manager->persist(
                (new MaterielStock())
                    ->setNom($nom)
                    ->setQuantite($quantite)
                    ->setSeuilMinimal($seuilMinimal),
            );
        }
    }

    /**
     * @param array<string, Employe> $employes
     */
    private function createCreneauxTravail(ObjectManager $manager, array $employes, \DateTimeImmutable $today): void
    {
        $techniciens = array_filter(
            $employes,
            static fn (Employe $employe): bool => $employe->getPoste() === Poste::Technicien,
        );
        $firstMonday = $today->modify('monday this week');

        for ($dayOffset = 0; $dayOffset < 21; ++$dayOffset) {
            $date = $firstMonday->modify(sprintf('+%d days', $dayOffset));
            if ((int) $date->format('N') > 5) {
                continue;
            }

            foreach ($techniciens as $technicien) {
                foreach ([['08:30', '12:30'], ['13:30', '17:30']] as [$start, $end]) {
                    $manager->persist(
                        (new CreneauTravail())
                            ->setEmploye($technicien)
                            ->setDebut(new \DateTimeImmutable($date->format('Y-m-d') . ' ' . $start))
                            ->setFin(new \DateTimeImmutable($date->format('Y-m-d') . ' ' . $end)),
                    );
                }
            }
        }
    }

    /**
     * @param array<string, Employe> $employes
     */
    private function createIndisponibilites(ObjectManager $manager, array $employes, \DateTimeImmutable $today): void
    {
        $firstMonday = $today->modify('monday this week');
        $definitions = [
            [
                $employes['claire'],
                TypeIndisponibilite::Conge,
                $firstMonday->modify('+8 days')->setTime(8, 30),
                $firstMonday->modify('+8 days')->setTime(17, 30),
            ],
            [
                $employes['malik'],
                TypeIndisponibilite::Maladie,
                $firstMonday->modify('+10 days')->setTime(13, 30),
                $firstMonday->modify('+10 days')->setTime(17, 30),
            ],
        ];

        foreach ($definitions as [$employe, $type, $debut, $fin]) {
            $manager->persist(
                (new Indisponibilite())
                    ->setEmploye($employe)
                    ->setType($type)
                    ->setDebut($debut)
                    ->setFin($fin),
            );
        }
    }

    private function createIntervention(
        ObjectManager $manager,
        Appareil $appareil,
        Employe $employe,
        ProblemeIntervention $probleme,
        StatutIntervention $statut,
        \DateTimeImmutable $dateDiagnostic,
        int $dureeDiagnostic,
        string $detailDiagnostic,
        \DateTimeImmutable $dateOperation,
        int $dureeOperation,
        string $description,
    ): Intervention {
        $intervention = (new Intervention())
            ->setProbleme($probleme)
            ->setStatut($statut)
            ->setDescription($description)
            ->setAppareil($appareil)
            ->setEmploye($employe);
        $diagnostic = (new Diagnostic())
            ->setDate($dateDiagnostic)
            ->setDuree($dureeDiagnostic)
            ->setDiagnostic($detailDiagnostic);
        $operation = (new Operation())
            ->setDateOperation($dateOperation)
            ->setDureeOperation($dureeOperation);

        $intervention->setDiagnostic($diagnostic);
        $intervention->setOperation($operation);

        $manager->persist($intervention);
        $manager->persist($diagnostic);
        $manager->persist($operation);

        return $intervention;
    }

    /**
     * @param array<string, Intervention> $interventions
     */
    private function createConsommations(ObjectManager $manager, array $interventions): void
    {
        $definitions = [
            [$interventions['controle-ecg'], 'Électrodes ECG à usage unique', 4],
            [$interventions['controle-ecg'], 'Gel conducteur médical', 1],
            [$interventions['maintenance-aspirateur'], 'Filtre antibactérien pour aspiration', 1],
            [$interventions['maintenance-aspirateur'], 'Gants nitrile non poudrés', 2],
            [$interventions['reparation-monitoring'], 'Connecteur d’alimentation médical', 1],
            [$interventions['reparation-monitoring'], 'Lingettes désinfectantes médicales', 3],
            [$interventions['annulation-pompe'], 'Gants nitrile non poudrés', 1],
            [$interventions['annulation-pompe'], 'Joint silicone pour pompe', 1],
        ];

        foreach ($definitions as [$intervention, $nom, $quantite]) {
            $manager->persist(
                (new MaterielIntervention())
                    ->setIntervention($intervention)
                    ->setNom($nom)
                    ->setQuantite($quantite),
            );
        }
    }
}
