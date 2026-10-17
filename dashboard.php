<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$pdo = new PDO(
    'mysql:host=localhost;dbname=visastudy;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = ['nouveau', 'contacte', 'relance', 'cloture'];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(nom LIKE :nom OR email LIKE :email OR tel LIKE :tel)';
    $params[':nom'] = '%' . $search . '%';
    $params[':email'] = '%' . $search . '%';
    $params[':tel'] = '%' . $search . '%';
}

if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'statut = :statut';
    $params[':statut'] = $status;
}

$sql = 'SELECT id, nom, tel, whatsapp, email, pays, niveau, filiere,
               langue_enseignement, budget, message, statut, created_at
        FROM demandes';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll();

$total = (int) $pdo->query(
    'SELECT COUNT(*) FROM demandes'
)->fetchColumn();

$nouveaux = (int) $pdo->query(
    "SELECT COUNT(*) FROM demandes WHERE statut = 'nouveau'"
)->fetchColumn();

$contactes = (int) $pdo->query(
    "SELECT COUNT(*) FROM demandes WHERE statut = 'contacte'"
)->fetchColumn();

$clotures = (int) $pdo->query(
    "SELECT COUNT(*) FROM demandes WHERE statut = 'cloture'"
)->fetchColumn();

function statutLabel($statut): string {
    return match ($statut) {
        'nouveau' => 'Nouveau',
        'contacte' => 'Contacté',
        'relance' => 'Relance',
        'cloture' => 'Clôturé',
        default => ucfirst((string)$statut),
    };
}

function statutClass($statut): string {
    return match ($statut) {
        'nouveau' => 'new',
        'contacte' => 'progress',
        'relance' => 'cancelled',
        'cloture' => 'done',
        default => 'neutral',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Admin | Visa Study</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #0a1f44;
    --blue: #1a56db;
    --blue-soft: #e8f0fe;
    --ink: #14213d;
    --muted: #69758b;
    --line: #e7ebf2;
    --bg: #f5f7fb;
    --white: #fff;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--ink);
    background: var(--bg);
}

a { color: inherit; text-decoration: none; }

.sidebar {
    width: 245px;
    position: fixed;
    inset: 0 auto 0 0;
    padding: 27px 18px;
    background: var(--navy);
    color: white;
    display: flex;
    flex-direction: column;
    z-index: 10;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 32px;
    font-size: 18px;
    font-weight: 800;
}

.brand-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: linear-gradient(135deg, #326bea, #6a93f4);
}

.nav-label {
    padding: 12px 10px;
    color: #aab9d3;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.4px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    margin-bottom: 6px;
    border-radius: 11px;
    color: #dce6f8;
    font-size: 12px;
    font-weight: 700;
}

.nav-link.active {
    color: white;
    background: #ffffff1c;
}

.nav-icon {
    width: 22px;
    text-align: center;
    font-size: 17px;
}

.sidebar-bottom {
    margin-top: auto;
    padding: 16px 10px 0;
    border-top: 1px solid #ffffff20;
}

.admin-name {
    margin-bottom: 14px;
    font-size: 12px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.admin-name small {
    display: block;
    margin-top: 5px;
    color: #aab9d3;
    font-size: 10px;
    font-weight: 500;
}

.logout {
    display: block;
    padding: 11px 12px;
    color: #ffe1e1;
    border-radius: 10px;
    background: #ffffff10;
    font-size: 12px;
    font-weight: 700;
}

.main {
    min-height: 100vh;
    margin-left: 245px;
    padding: 30px 34px 45px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 30px;
}

.eyebrow {
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

h1 {
    margin: 8px 0 0;
    color: var(--navy);
    font-size: 27px;
    letter-spacing: -1px;
}

.subtitle {
    margin-top: 8px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}

.date-label {
    padding: 12px 15px;
    border: 1px solid var(--line);
    background: white;
    border-radius: 12px;
    color: var(--muted);
    font-size: 11px;
    white-space: nowrap;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 25px;
}

.stat {
    padding: 21px;
    background: white;
    border: 1px solid var(--line);
    border-radius: 17px;
    box-shadow: 0 5px 20px #0a1f4405;
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.stat-title {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
}

.stat-icon {
    width: 37px;
    height: 37px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: var(--blue-soft);
    color: var(--blue);
    font-size: 17px;
}

.stat-value {
    margin-top: 17px;
    color: var(--navy);
    font-size: 29px;
    font-weight: 800;
    letter-spacing: -1px;
}

.stat-caption {
    margin-top: 5px;
    color: var(--muted);
    font-size: 10px;
}

.panel {
    overflow: hidden;
    background: white;
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: 0 5px 20px #0a1f4405;
}

.panel-header {
    padding: 23px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border-bottom: 1px solid var(--line);
}

.panel-header h2 {
    margin: 0;
    color: var(--navy);
    font-size: 16px;
}

.panel-header p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 11px;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--line);
}

.filters input,
.filters select {
    min-height: 42px;
    padding: 0 12px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: white;
    color: var(--ink);
    font-family: inherit;
    font-size: 11px;
    outline: none;
}

.filters input {
    flex: 1;
    min-width: 170px;
}

.filters input:focus,
.filters select:focus {
    border-color: var(--blue);
}

.filter-btn {
    padding: 0 17px;
    border: 0;
    border-radius: 10px;
    background: var(--blue);
    color: white;
    font-family: inherit;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
}

.reset-btn {
    display: inline-flex;
    align-items: center;
    padding: 0 13px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

th {
    padding: 15px 17px;
    background: #f9faff;
    color: var(--muted);
    text-align: left;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

td {
    padding: 16px 17px;
    border-top: 1px solid #edf0f5;
    font-size: 11px;
    vertical-align: middle;
}

tbody tr:hover {
    background: #fbfcff;
}

.student {
    min-width: 150px;
}

.student strong {
    display: block;
    color: var(--navy);
    font-size: 11px;
}

.student small {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    font-size: 10px;
}

.country {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 8px;
    background: #f0f4fa;
    color: #334767;
    font-size: 10px;
    font-weight: 700;
}

.status {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.status.new { color: #a65a00; background: #fff3d9; }
.status.progress { color: #1d4ed8; background: #e8f0ff; }
.status.done { color: #137547; background: #e1f7eb; }
.status.cancelled { color: #b42318; background: #ffeded; }
.status.neutral { color: #596579; background: #edf0f5; }

.empty {
    padding: 55px 20px;
    text-align: center;
    color: var(--muted);
    font-size: 12px;
}

.empty-icon {
    margin-bottom: 12px;
    font-size: 30px;
}

.panel-footer {
    padding: 15px 24px;
    border-top: 1px solid var(--line);
    color: var(--muted);
    font-size: 10px;
}

@media (max-width: 1150px) {
    .sidebar { width: 205px; }
    .main { margin-left: 205px; padding: 26px 22px 35px; }
    .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .sidebar {
        position: static;
        width: 100%;
        padding: 14px 16px;
    }

    .brand { padding: 0; }
    .sidebar .nav-label,
    .sidebar .nav-link { display: none; }
    .sidebar-bottom { display: none; }

    .main {
        margin-left: 0;
        padding: 24px 15px 35px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: 22px;
    }

    h1 { font-size: 24px; }
    .stats { gap: 10px; }
    .stat { padding: 16px; }
    .stat-value { font-size: 25px; }
    .stat-title { font-size: 10px; }
    .panel-header { padding: 19px 16px; }
    .filters { padding: 15px 16px; }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">VS</div>
        <span>Visa Study</span>
    </div>

    <div class="nav-label">MENU PRINCIPAL</div>

    <a href="dashboard.php" class="nav-link active">
        <span class="nav-icon">▦</span>
        Dashboard
    </a>

    <a href="dashboard.php" class="nav-link">
        <span class="nav-icon">▤</span>
        Demandes étudiants
    </a>

    <div class="sidebar-bottom">
        <div class="admin-name">
            <?= h($_SESSION['admin_nom'] ?? 'Administrateur') ?>
            <small>Administrateur</small>
        </div>
        <a class="logout" href="logout.php">↪ &nbsp; Déconnexion</a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <div class="eyebrow">Vue d'ensemble</div>
            <h1>Dashboard</h1>
            <div class="subtitle">
                Bienvenue <?= h($_SESSION['admin_nom'] ?? 'Admin') ?>,
                voici le suivi des demandes étudiants.
            </div>
        </div>

        <div class="date-label">
            ◷ &nbsp; <?= date('d/m/Y') ?>
        </div>
    </div>

    <section class="stats">
        <div class="stat">
            <div class="stat-top">
                <span class="stat-title">Total des demandes</span>
                <span class="stat-icon">▤</span>
            </div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-caption">Toutes les demandes enregistrées</div>
        </div>

        <div class="stat">
            <div class="stat-top">
                <span class="stat-title">Nouvelles demandes</span>
                <span class="stat-icon">✦</span>
            </div>
            <div class="stat-value"><?= $nouveaux ?></div>
            <div class="stat-caption">À examiner</div>
        </div>

        <div class="stat">
            <div class="stat-top">
                <span class="stat-title">Contactés</span>
                <span class="stat-icon">◷</span>
            </div>
            <div class="stat-value"><?= $contactes ?></div>
            <div class="stat-caption">Étudiants déjà contactés</div>
        </div>

        <div class="stat">
            <div class="stat-top">
                <span class="stat-title">Clôturées</span>
                <span class="stat-icon">✓</span>
            </div>
            <div class="stat-value"><?= $clotures ?></div>
            <div class="stat-caption">Dossiers clôturés</div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Demandes étudiants</h2>
                <p>Consultez et recherchez les demandes reçues.</p>
            </div>
        </div>

        <form method="get" class="filters">
            <input
                type="search"
                name="search"
                placeholder="Rechercher nom, email ou téléphone..."
                value="<?= h($search) ?>"
            >

            <select name="status">
                <option value="">Toutes les statuts</option>
                <option value="nouveau" <?= $status === 'nouveau' ? 'selected' : '' ?>>Nouveau</option>
                <option value="contacte" <?= $status === 'contacte' ? 'selected' : '' ?>>Contacté</option>
                <option value="relance" <?= $status === 'relance' ? 'selected' : '' ?>>Relance</option>
                <option value="cloture" <?= $status === 'cloture' ? 'selected' : '' ?>>Clôturé</option>
            </select>

            <button type="submit" class="filter-btn">Rechercher</button>
            <a href="dashboard.php" class="reset-btn">Réinitialiser</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ÉTUDIANT</th>
                        <th>TÉLÉPHONE</th>
                        <th>PAYS</th>
                        <th>NIVEAU</th>
                        <th>FILIÈRE</th>
                        <th>STATUT</th>
                        <th>DATE</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$demandes): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty">
                                <div class="empty-icon">▤</div>
                                Aucune demande trouvée.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($demandes as $d): ?>
                    <tr>
                        <td>
                            <div class="student">
                                <strong><?= h($d['nom']) ?></strong>
                                <small><?= h($d['email']) ?></small>
                            </div>
                        </td>

                        <td><?= h($d['tel']) ?></td>

                        <td>
                            <span class="country"><?= h($d['pays']) ?></span>
                        </td>

                        <td><?= h($d['niveau']) ?></td>
                        <td><?= h($d['filiere']) ?></td>

                        <td>
                            <span class="status <?= h(statutClass($d['statut'])) ?>">
                                <?= h(statutLabel($d['statut'])) ?>
                            </span>
                        </td>

                        <td><?= h($d['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="panel-footer">
            <?= count($demandes) ?> demande(s) affichée(s)
            <?php if ($search !== '' || in_array($status, $allowedStatuses, true)): ?>
                — résultat de votre recherche
            <?php endif; ?>
        </div>
    </section>
</main>

</body>
</html>
