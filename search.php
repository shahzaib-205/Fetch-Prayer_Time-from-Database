<?php
$server = "localhost";
$username = "root";
$password = "";
$database = "searchbar";
$conn = mysqli_connect($server, $username, $password, $database) or die("Connection failed: " . mysqli_connect_error());

$search_query = $_GET['prayer_time'] ?? '';
$prayer_times = [];

if ($search_query) {
    $stmt = $conn->prepare("SELECT pt.*, c.name AS city_name, co.name AS country_name 
                            FROM prayer_times pt 
                            INNER JOIN cities c ON pt.city_id = c.id 
                            INNER JOIN countries co ON c.country_id = co.id 
                            WHERE CONCAT(c.name, ', ', co.name) LIKE ?");
    $search_query_param = '%' . $search_query . '%';
    $stmt->bind_param('s', $search_query_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $prayer_times = $result->fetch_all(MYSQLI_ASSOC);
}

$suggestions = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entityBody = file_get_contents('php://input');
    $searchQuery = json_decode($entityBody, true);
    $searchQuery = $searchQuery['searchQuery'];

    $stmt = $conn->prepare("SELECT c.name AS city_name, co.name AS country_name 
                            FROM cities c 
                            INNER JOIN countries co ON c.country_id = co.id 
                            WHERE c.name LIKE ? OR co.name LIKE ?");
    $searchQueryParam = '%' . $searchQuery . '%';
    $stmt->bind_param('ss', $searchQueryParam, $searchQueryParam);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'city' => $row['city_name'],
        ];
        $suggestions[] = [
            'city' => $row['city_name'],
            'country' => $row['country_name'],
        ];
    }

    echo json_encode($suggestions);
    exit;
}

function usfirst($str) {
    return ucwords(strtolower($str));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prayer Times</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            width: 100%;
            max-width: 800px;
        }
        #search-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 18px;
        }
        #search_query {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 10px;
            margin-left: -10px;
            border: 1px solid lightskyblue;
        }
        #search-form button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        #search-form button:hover {
            background-color: #0056b3;
        }
        #prayer-times {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .prayer-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border: 1px solid lightskyblue;
        }
        .prayer-card h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .prayer-card ul {
            list-style: none;
            padding: 0;
        }
        .prayer-card li {
            font-size: 16px;
            margin-bottom: 8px;
        }
        .prayer-card li {
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .prayer-card li span:last-child {
            flex-shrink: 0;
            width: 60px;
            text-align: center;
        }

        .prayer-card li span:last-child {
            text-align: right;
        }
        .prayer-card li span:nth-child(2) {
            margin-left: auto;
        }
    </style>
</head>
<body>

<div class="card" id="search-form-card">
    <form method="GET" action="" id="search-form">
        <label for="search_query" style="text-align:center;">Enter City name to check Prayer Time:</label>
        <input type="text" name="prayer_time" id="search_query" list="search-suggestions" autocomplete="off" placeholder="Enter City Name" required>
        <datalist id="search-suggestions"></datalist>
        <button type="submit" style="float:right">Search</button>
    </form>
</div>

<div class="card" id="prayer-times-card">
    <div id="prayer-times">
        <?php if ($prayer_times): ?>
            <h2>Prayer Times for Searched Location:</h2>
            <?php foreach ($prayer_times as $prayer): ?>
                <div class="prayer-card">
                    <h3><?= usfirst(htmlspecialchars($prayer['city_name'])) ?>, <?= usfirst(htmlspecialchars($prayer['country_name'])) ?>:</h3>
                    <ul>
                        <li><strong>Fajr:</strong> <?= date('h:i A', strtotime($prayer['fajr'])) ?></li>
                        <li><strong>Dhuhr:</strong><?= date('h:i A', strtotime($prayer['dhuhr'])) ?></li>
                        <li><strong>Asr:</strong><?= date('h:i A', strtotime($prayer['asr'])) ?></li>
                        <li><strong>Maghrib:</strong><?= date('h:i A', strtotime($prayer['maghrib'])) ?></li>
                        <li><strong>Isha:</strong><?= date('h:i A', strtotime($prayer['isha'])) ?></li>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php elseif ($search_query): ?>
            <p>No prayer times found for "<?= htmlspecialchars($search_query) ?>".</p>
            <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('search_query').addEventListener('input', function() {
        const searchQuery = this.value.trim();
        if (searchQuery.length < 2) {
            document.getElementById('search-suggestions').innerHTML = '';
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    try {
                        const suggestions = JSON.parse(xhr.responseText);
                        updateSuggestions(suggestions);
                    } catch (error) {
                        console.error('Error parsing JSON:', error);
                    }
                } else {
                    console.error('Error:', xhr.status);
                }
            }
        };
        xhr.open('POST', 'search.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({ searchQuery }));
    });

    document.getElementById('search_query').addEventListener('change', function() {
        setTimeout(function() {
            document.getElementById('search-suggestions').innerHTML = '';
        }, 50);
    });

    function updateSuggestions(suggestions) {
        const datalist = document.getElementById('search-suggestions');
        datalist.innerHTML = '';
        if (suggestions.length > 0) {
            suggestions.forEach(suggestion => {
                const option = document.createElement('option');
                if (suggestion.country) {
                    option.textContent = suggestion.city + ', ' + suggestion.country;
                } else {
                    option.textContent = suggestion.city;
                }
                datalist.appendChild(option);
            });
        }
    }
    document.getElementById('search-form').addEventListener('submit', function(event) {
        event.preventDefault();
        const searchQuery = document.getElementById('search_query').value;
        const url = new URL(window.location);
        url.searchParams.set('prayer_time', searchQuery);
        window.history.pushState({}, '', url);
        this.submit();
    });
</script>

</body>
</html>