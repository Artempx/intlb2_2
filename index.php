<?php
require 'vendor/autoload.php';

$client = new MongoDB\Client("mongodb://root:root@mongo:27017");
$db = $client->car_rental;

// Колекції
$carsCollection = $db->cars;
$rentalsCollection = $db->rentals;

// Отримання всіх наявних авто
$allCarsCursor = $carsCollection->find();
$allCars = iterator_to_array($allCarsCursor, false);

// Фільтрація автомобілів за пробігом
$mileageLimit = null;
$filteredCars = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mileage'])) {
    $mileageLimit = (int) $_POST['mileage']; 

    $query = ['mileage' => ['$lt' => $mileageLimit]];
    $cursor = $carsCollection->find($query);
    $filteredCars = iterator_to_array($cursor, false);
}

// Розрахунок доходу за дату
$totalIncome = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rental_date'])) {
    $selectedDate = strtotime($_POST['rental_date']);

    $query = [
        'start_date' => ['$lte' => $selectedDate],
        '$or' => [
            ['end_date' => ['$gte' => $selectedDate]],
            ['end_date' => ['$exists' => false]]
        ]
    ];

    $cursor = $rentalsCollection->find($query);
    $totalIncome = 0;

    foreach ($cursor as $rental) {
        $totalIncome += $rental['price'];
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прокат автомобілів</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        button { cursor: pointer; padding: 5px 10px; margin-top: 10px; }
        .container { display: flex; justify-content: space-between; gap: 20px; }
        .box { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>📜 Історія результатів</h2>
    <button onclick="clearHistory()">🗑 Очистити історію</button>
    <div id="query-history">
        <p>Немає збережених результатів</p>
    </div>

    <div class="container">
        <div class="box">
            <h2>🚗 Всі доступні автомобілі</h2>
            <table>
                <tr>
                    <th>Марка</th>
                    <th>Рік випуску</th>
                    <th>Пробіг</th>
                    <th>Стан</th>
                </tr>
                <?php foreach ($allCars as $car): ?>
                    <tr>
                        <td><?= htmlspecialchars($car['brand']) ?></td>
                        <td><?= htmlspecialchars($car['year']) ?></td>
                        <td><?= number_format($car['mileage'], 0, ',', ' ') ?> км</td>
                        <td><?= htmlspecialchars($car['condition']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <h2>🔎 Фільтр автомобілів за пробігом</h2>
    <form method="POST" onsubmit="saveResult('mileage', this.mileage.value, document.getElementById('filtered-cars').innerHTML)">
        <input type="number" name="mileage" value="<?= htmlspecialchars($mileageLimit ?? '') ?>" required>
        <button type="submit">Показати</button>
    </form>

    <div id="filtered-cars">
        <?php if ($mileageLimit !== null): ?>
            <h2>🚘 Автомобілі з пробігом менше <?= $mileageLimit ?> км</h2>

            <?php if (count($filteredCars) > 0): ?>
                <table>
                    <tr>
                        <th>Марка</th>
                        <th>Рік випуску</th>
                        <th>Пробіг</th>
                        <th>Стан</th>
                    </tr>
                    <?php foreach ($filteredCars as $car): ?>
                        <tr>
                            <td><?= htmlspecialchars($car['brand']) ?></td>
                            <td><?= htmlspecialchars($car['year']) ?></td>
                            <td><?= number_format($car['mileage'], 0, ',', ' ') ?> км</td>
                            <td><?= htmlspecialchars($car['condition']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>🚘 Немає автомобілів з пробігом менше <?= number_format($mileageLimit, 0, ',', ' ') ?> км.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <h2>💰 Введіть дату для розрахунку доходу:</h2>
    <form method="POST" onsubmit="saveResult('rental_date', this.rental_date.value, document.getElementById('rental-income').innerHTML)">
        <input type="date" name="rental_date" required>
        <button type="submit">Розрахувати дохід</button>
    </form>

    <div id="rental-income">
        <?php if ($totalIncome !== null): ?>
            <h2>💰 Дохід за <?= htmlspecialchars($_POST['rental_date']) ?>: 
                <?= number_format($totalIncome, 0, ',', ' ') ?> грн.</h2>
        <?php endif; ?>
    </div>

    <script>
        function saveResult(type, value, resultHTML) {
            let history = JSON.parse(localStorage.getItem("queryResults")) || [];

            history.push({
                type: type,
                value: value,
                result: resultHTML,
                date: new Date().toLocaleString()
            });

            localStorage.setItem("queryResults", JSON.stringify(history));

            updateHistoryUI();
        }

        function updateHistoryUI() {
            const historyDiv = document.getElementById("query-history");
            const history = JSON.parse(localStorage.getItem("queryResults")) || [];

            if (history.length > 0) {
                historyDiv.innerHTML = "";
                history.forEach(item => {
                    const entry = document.createElement("div");
                    entry.innerHTML = `
                        <h3>📌 ${item.date} | ${item.type}: ${item.value}</h3>
                        <div>${item.result}</div>
                        <hr>
                    `;
                    historyDiv.appendChild(entry);
                });
            } else {
                historyDiv.innerHTML = "<p>Немає збережених результатів</p>";
            }
        }

        function clearHistory() {
            localStorage.removeItem("queryResults");
            updateHistoryUI();
        }

        document.addEventListener("DOMContentLoaded", updateHistoryUI);
    </script>
</body>
</html>
