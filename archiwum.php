
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "archiwum";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

$komunikat = "";
$typ = "sukces";

$statusy = [
    "Znaleziony",
    "Oczekuje na właściciela",
    "Zwrócony",
    "Przekazany do utylizacji"
];

function h($tekst) {
    return htmlspecialchars((string)$tekst, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $akcja = $_POST["akcja"] ?? "";

    if ($akcja == "dodaj") {
        $nazwa = trim($_POST["nazwa"] ?? "");
        $kategoria = (int)($_POST["kategoria"] ?? 0);
        $miejsce = trim($_POST["miejsce"] ?? "");
        $data = $_POST["data"] ?? "";
        $wydarzenie = (int)($_POST["wydarzenie"] ?? 0);

        if ($nazwa == "" || $miejsce == "" || $data == ""
            || $kategoria < 1 || $wydarzenie < 1) {
            $komunikat = "Wypełnij wszystkie pola.";
            $typ = "blad";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)
            || !checkdate((int)substr($data, 5, 2),
                (int)substr($data, 8, 2),
                (int)substr($data, 0, 4))) {
            $komunikat = "Podaj prawidłową datę.";
            $typ = "blad";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO przedmioty
                (nazwa, kategoria_id, miejsce, data_znalezienia,
                 wydarzenie_id, status)
                VALUES (?, ?, ?, ?, ?, 'Znaleziony')"
            );
            $stmt->bind_param(
                "sissi",
                $nazwa,
                $kategoria,
                $miejsce,
                $data,
                $wydarzenie
            );
            $stmt->execute();
            $komunikat = "Dodano nowy przedmiot.";
        }
    }

    if ($akcja == "edytuj") {
        $id = (int)($_POST["id"] ?? 0);
        $status = $_POST["status"] ?? "";

        if ($id < 1 || !in_array($status, $statusy, true)) {
            $komunikat = "Nieprawidłowe dane.";
            $typ = "blad";
        } else {
            $stmt = $conn->prepare(
                "UPDATE przedmioty SET status = ? WHERE id = ?"
            );
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();

            if ($stmt->affected_rows >= 0) {
                $komunikat = "Zmieniono status przedmiotu.";
            }
        }
    }

    if ($akcja == "usun") {
        $id = (int)($_POST["id"] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare(
                "DELETE FROM przedmioty WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $komunikat = "Usunięto przedmiot.";
            } else {
                $komunikat = "Nie znaleziono przedmiotu.";
                $typ = "blad";
            }
        }
    }
}

$szukaj = trim($_GET["szukaj"] ?? "");
$kat = (int)($_GET["kat"] ?? 0);
$miejsceFiltr = trim($_GET["miejsce"] ?? "");
$sort = $_GET["sort"] ?? "nazwa";

$sortowanie = [
    "nazwa" => "p.nazwa ASC",
    "data" => "p.data_znalezienia DESC",
    "status" => "p.status ASC"
];

$order = $sortowanie[$sort] ?? $sortowanie["nazwa"];

$sql = "SELECT p.id, p.nazwa, k.nazwa AS kategoria,
               p.miejsce, p.data_znalezienia,
               w.nazwa AS wydarzenie, p.status, p.kategoria_id
        FROM przedmioty p
        JOIN kategorie k ON p.kategoria_id = k.id
        JOIN wydarzenia w ON p.wydarzenie_id = w.id
        WHERE p.nazwa LIKE ?
        AND (? = 0 OR p.kategoria_id = ?)
        AND p.miejsce LIKE ?
        ORDER BY $order";

$stmt = $conn->prepare($sql);
$wzorzec = "%" . $szukaj . "%";
$miejsceWzorzec = "%" . $miejsceFiltr . "%";
$stmt->bind_param(
    "siis",
    $wzorzec,
    $kat,
    $kat,
    $miejsceWzorzec
);
$stmt->execute();
$przedmioty = $stmt->get_result();

$kategorie = $conn->query("SELECT * FROM kategorie ORDER BY nazwa");
$wydarzenia = $conn->query("SELECT * FROM wydarzenia ORDER BY nazwa");

$liczba = $conn->query(
    "SELECT COUNT(*) AS ile FROM przedmioty"
)->fetch_assoc()["ile"];

$zwrocone = $conn->query(
    "SELECT COUNT(*) AS ile FROM przedmioty WHERE status = 'Zwrócony'"
)->fetch_assoc()["ile"];

$opinia = $conn->query(
    "SELECT k.nazwa, COUNT(p.id) AS ile
     FROM kategorie k
     JOIN przedmioty p ON p.kategoria_id = k.id
     GROUP BY k.id, k.nazwa
     ORDER BY ile DESC, k.nazwa ASC
     LIMIT 1"
)->fetch_assoc();

$topKategoria = $opinia ? $opinia["nazwa"] : "Brak danych";
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archiwum zaginionych przedmiotów</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="naglowek">
        <h1>Archiwum przedmiotów</h1>
        <p>System ewidencji rzeczy znalezionych podczas wydarzeń</p>
    </div>
</header>

<nav>
    <a href="#lista">Lista przedmiotów</a>
    <a href="#dodaj">Dodaj przedmiot</a>
    <a href="#raport">Raport</a>
</nav>

<main>

    <?php if ($komunikat != ""): ?>
        <div class="komunikat <?= h($typ) ?>">
            <?= h($komunikat) ?>
        </div>
    <?php endif; ?>

    <section class="statystyki" id="raport">
        <div class="karta">
            <span>Wszystkie przedmioty</span>
            <strong><?= $liczba ?></strong>
        </div>
        <div class="karta">
            <span>Zwrócone właścicielom</span>
            <strong><?= $zwrocone ?></strong>
        </div>
        <div class="karta">
            <span>Najczęstsza kategoria</span>
            <strong><?= h($topKategoria) ?></strong>
        </div>
    </section>

    <section>
        <h2>Wyszukaj przedmiot</h2>

        <form method="GET" class="formularz">
            <div>
                <label for="szukaj">Nazwa przedmiotu</label>
                <input type="text" id="szukaj" name="szukaj"
                    value="<?= h($szukaj) ?>" placeholder="Np. telefon">
            </div>

            <div>
                <label for="kat">Kategoria</label>
                <select name="kat" id="kat">
                    <option value="0">Wszystkie kategorie</option>
                    <?php
                    $kategorie->data_seek(0);
                    while ($k = $kategorie->fetch_assoc()):
                    ?>
                        <option value="<?= $k["id"] ?>"
                            <?= $kat == $k["id"] ? "selected" : "" ?>>
                            <?= h($k["nazwa"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label for="miejsce">Miejsce znalezienia</label>
                <input type="text" id="miejsce" name="miejsce"
                    value="<?= h($miejsceFiltr) ?>"
                    placeholder="Np. parking">
            </div>

            <div>
                <label for="sort">Sortowanie</label>
                <select name="sort" id="sort">
                    <option value="nazwa" <?= $sort == "nazwa" ? "selected" : "" ?>>Według nazwy</option>
                    <option value="data" <?= $sort == "data" ? "selected" : "" ?>>Od najnowszych</option>
                    <option value="status" <?= $sort == "status" ? "selected" : "" ?>>Według statusu</option>
                </select>
            </div>

            <button type="submit">Szukaj</button>
            <a class="przycisk drugi" href="index.php">Wyczyść filtry</a>
        </form>
    </section>

    <section id="lista">
        <h2>Lista znalezionych przedmiotów</h2>

        <div class="tabela">
            <table>
                <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Kategoria</th>
                        <th>Miejsce</th>
                        <th>Data</th>
                        <th>Wydarzenie</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($przedmioty->num_rows > 0): ?>
                    <?php while ($p = $przedmioty->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($p["nazwa"]) ?></td>
                            <td><?= h($p["kategoria"]) ?></td>
                            <td><?= h($p["miejsce"]) ?></td>
                            <td><?= h($p["data_znalezienia"]) ?></td>
                            <td><?= h($p["wydarzenie"]) ?></td>
                            <td>
                                <span class="status"><?= h($p["status"]) ?></span>
                            </td>
                            <td class="akcje">
                                <form method="POST" class="edycja">
                                    <input type="hidden" name="akcja" value="edytuj">
                                    <input type="hidden" name="id" value="<?= $p["id"] ?>">

                                    <select name="status" aria-label="Nowy status">
                                        <?php foreach ($statusy as $s): ?>
                                            <option value="<?= h($s) ?>"
                                                <?= $p["status"] == $s ? "selected" : "" ?>>
                                                <?= h($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit">Zmień</button>
                                </form>

                                <form method="POST"
                                    onsubmit="return confirm('Czy na pewno usunąć ten przedmiot?');">
                                    <input type="hidden" name="akcja" value="usun">
                                    <input type="hidden" name="id" value="<?= $p["id"] ?>">
                                    <button type="submit" class="usun">Usuń</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Brak przedmiotów spełniających podane kryteria.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="dodaj">
        <h2>Dodaj znaleziony przedmiot</h2>

        <form method="POST" class="formularz">
            <input type="hidden" name="akcja" value="dodaj">

            <div>
                <label for="nazwa">Nazwa przedmiotu</label>
                <input type="text" id="nazwa" name="nazwa"
                    maxlength="100" required>
            </div>

            <div>
                <label for="kategoria">Kategoria</label>
                <select name="kategoria" id="kategoria" required>
                    <option value="">Wybierz kategorię</option>
                    <?php
                    $kategorie->data_seek(0);
                    while ($k = $kategorie->fetch_assoc()):
                    ?>
                        <option value="<?= $k["id"] ?>">
                            <?= h($k["nazwa"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label for="miejsce_dodaj">Miejsce znalezienia</label>
                <input type="text" id="miejsce_dodaj" name="miejsce"
                    maxlength="100" required>
            </div>

            <div>
                <label for="data">Data znalezienia</label>
                <input type="date" id="data" name="data" required>
            </div>

            <div>
                <label for="wydarzenie">Wydarzenie</label>
                <select name="wydarzenie" id="wydarzenie" required>
                    <option value="">Wybierz wydarzenie</option>
                    <?php while ($w = $wydarzenia->fetch_assoc()): ?>
                        <option value="<?= $w["id"] ?>">
                            <?= h($w["nazwa"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit">Dodaj przedmiot</button>
        </form>
    </section>

</main>

<footer>
    <p>Archiwum zaginionych przedmiotów</p>
</footer>

</body>
</html>
