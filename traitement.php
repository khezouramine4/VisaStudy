<?php
declare(strict_types=1);

session_start();

/* =========================
   CONFIGURATION DATABASE
========================= */
const DB_HOST = 'localhost';
const DB_NAME = 'visastudy';
const DB_USER = 'root';
const DB_PASS = '';

const PAGE_FORMULAIRE = 'contact.html';

/* =========================
   REDIRECTION
========================= */
function retour(string $status): never
{
    header(
        'Location: ' . PAGE_FORMULAIRE . '?status=' . urlencode($status),
        true,
        303
    );
    exit;
}

/* =========================
   NETTOYAGE DES DONNEES
========================= */
function nettoyer(?string $valeur, int $max = 255): string
{
    $valeur = trim($valeur ?? '');
    $valeur = str_replace("\0", '', $valeur);

    return mb_substr($valeur, 0, $max, 'UTF-8');
}

/* =========================
   NORMALISATION TELEPHONE
========================= */
function normaliser_tel(string $tel): string
{
    $tel = trim($tel);

    // Garde uniquement les chiffres et le signe +
    $tel = preg_replace('/[^\d+]/', '', $tel) ?? '';

    // Si le numéro commence par 0, on ajoute l'indicatif marocain
    if (preg_match('/^0\d{9}$/', $tel)) {
        $tel = '+212' . substr($tel, 1);
    } elseif (preg_match('/^\d{9}$/', $tel)) {
        $tel = '+212' . $tel;
    }

    // Vérifie le format international
    if (!preg_match('/^\+?[1-9]\d{7,14}$/', $tel)) {
        return '';
    }

    return $tel;
}

/* =========================
   VERIFICATION DE LA REQUETE
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    retour('error');
}

/* =========================
   ANTI-SPAM (HONEYPOT)
========================= */
$website = nettoyer($_POST['website'] ?? '', 200);

if ($website !== '') {
    // On ne traite pas les soumissions suspectes
    retour('ok');
}

/* =========================
   RECUPERATION DES CHAMPS
========================= */
$nom = nettoyer($_POST['nom'] ?? '', 120);

$tel = normaliser_tel(
    nettoyer($_POST['tel'] ?? '', 30)
);

$whatsappBrut = nettoyer($_POST['whatsapp'] ?? '', 30);
$whatsapp = '';

if ($whatsappBrut !== '') {
    $whatsapp = normaliser_tel($whatsappBrut);

    if ($whatsapp === '') {
        retour('error');
    }
}

$email = nettoyer($_POST['email'] ?? '', 190);
$pays = nettoyer($_POST['pays'] ?? '', 30);
$niveau = nettoyer($_POST['niveau'] ?? '', 80);
$filiere = nettoyer($_POST['filiere'] ?? '', 80);
$langue = nettoyer($_POST['langue'] ?? '', 30);
$budget = nettoyer($_POST['budget'] ?? '', 40);
$message = nettoyer($_POST['message'] ?? '', 5000);
$consentement = $_POST['consentement'] ?? '';

/* =========================
   VALIDATION
========================= */
if (
    $nom === '' ||
    mb_strlen($nom, 'UTF-8') < 2 ||
    $tel === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $pays === '' ||
    $niveau === '' ||
    $filiere === '' ||
    $langue === '' ||
    $consentement !== 'oui'
) {
    retour('error');
}

/* =========================
   VALEURS AUTORISEES
========================= */
$paysAutorises = [
    'Russie',
    'Biélorussie',
    'À conseiller'
];

$languesAutorisees = [
    'Russe',
    'Anglais 100%',
    'Français',
    'À déterminer'
];

if (
    !in_array($pays, $paysAutorises, true) ||
    !in_array($langue, $languesAutorisees, true)
) {
    retour('error');
}

/* =========================
   CONNEXION MYSQL
========================= */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST .
        ';dbname=' . DB_NAME .
        ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    /* =========================
       INSERTION DE LA DEMANDE
    ========================= */
    $sql = "INSERT INTO demandes (
        nom,
        tel,
        whatsapp,
        email,
        pays,
        niveau,
        filiere,
        langue_enseignement,
        budget,
        message,
        consentement,
        ip
    ) VALUES (
        :nom,
        :tel,
        :whatsapp,
        :email,
        :pays,
        :niveau,
        :filiere,
        :langue,
        :budget,
        :message,
        :consentement,
        :ip
    )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nom' => $nom,
        ':tel' => $tel,
        ':whatsapp' => $whatsapp,
        ':email' => $email,
        ':pays' => $pays,
        ':niveau' => $niveau,
        ':filiere' => $filiere,
        ':langue' => $langue,
        ':budget' => $budget !== '' ? $budget : null,
        ':message' => $message !== '' ? $message : null,
        ':consentement' => 1,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // MySQL remplit automatiquement statut et created_at
    // grâce aux valeurs par défaut de la table demandes.

    retour('ok');

} catch (Throwable $e) {
    // L'erreur est enregistrée côté serveur, sans afficher
    // les informations sensibles au visiteur.
    error_log('Erreur traitement formulaire Visa Study Maroc : ' . $e->getMessage());

    retour('error');
}
