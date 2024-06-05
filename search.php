<?php
$server = "localhost";
$username = "root";
$password = "";
$database = "searchbar";
$conn = mysqli_connect($server, $username, $password, $database) or die("Connection failed: " . mysqli_connect_error());

$suggestions = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entityBody = file_get_contents('php://input');
    $request = json_decode($entityBody, true);
    $searchQuery = $request['searchQuery'] ?? '';
    $type = $request['type'] ?? 'suggestion';

    if ($type === 'suggestion') {
        $stmt = $conn->prepare("SELECT c.name AS city_name, co.name AS country_name
            FROM cities c
            INNER JOIN countries co ON c.country_id = co.id
            WHERE c.name LIKE ?");
        $searchQueryParam = "%" . $searchQuery . "%";
        $stmt->bind_param('s', $searchQueryParam);
    } else {
        $stmt = $conn->prepare("SELECT c.name AS city_name, co.name AS country_name
            FROM cities c
            INNER JOIN countries co ON c.country_id = co.id
            WHERE c.name = ?");
        $stmt->bind_param('s', $searchQuery);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = [
                'city' => $row['city_name'],
                'country' => $row['country_name'],
            ];
        }
    }

    $stmt->close();
    echo json_encode($suggestions);
    exit;
}

$conn->close();
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
            border: 1px solid lightskyblue;
            margin-left: -10px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .prayer-card li span:last-child {
            text-align: right;
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
    </div>
</div>

<script>
    document.getElementById('search_query').addEventListener('input', function() {
        const searchQuery = this.value.trim();
        if (searchQuery.length < 2) {
            document.getElementById('search-suggestions').innerHTML = '';
            return;
        }

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ searchQuery, type: 'suggestion' }),
        })
        .then(response => response.json())
        .then(data => updateSuggestions(data))
        .catch(error => console.error('Error fetching suggestions:', error));
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
                option.textContent = `${suggestion.city}, ${suggestion.country}`;
                datalist.appendChild(option);
            });
        }
    }

    document.getElementById('search-form').addEventListener('submit', function(event) {
        event.preventDefault();
        const searchQuery = document.getElementById('search_query').value;

        const [city, country] = searchQuery.split(',').map(str => str.trim());

        fetchPrayerTimes(city, country);

        const url = new URL(window.location.href);
        url.searchParams.set('page', city+'-prayer-times');
        window.history.pushState({}, '', url);
    });

    function fetchPrayerTimes(city, country = '') {
        if (!country) {
            country = '';
        }

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ searchQuery: city, type: 'validation' }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                let url = `https://api.aladhan.com/v1/timingsByCity?city=${encodeURIComponent(city)}&country=${encodeURIComponent(country)}&method=2`;
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 200 && data.data) {
                            displayPrayerTimes(data, city, country);
                        } else {
                            document.getElementById('prayer-times').innerHTML = `<p>No prayer times found for "${city}, ${country}".</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching prayer times:', error);
                        document.getElementById('prayer-times').innerHTML = `<p>No prayer times found for "${city}, ${country}".</p>`;
                    });
            } else {
                document.getElementById('prayer-times').innerHTML = `<p>No prayer times found for "${city}, ${country}".</p>`;
            }
        })
        .catch(error => {
            console.error('Error validating city:', error);
            document.getElementById('prayer-times').innerHTML = `<p>Error validating city "${city}".</p>`;
        });
    }

    function displayPrayerTimes(data, city, country) {
        const prayerTimes = data.data.timings;
        const currentDate = new Date().toLocaleDateString('en-US', { timeZone: data.data.meta.timezone });
        const currentTime = new Date().toLocaleTimeString('en-US', { timeZone: data.data.meta.timezone });

        const capitalize = (str) => {
            return str.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        };

        const capitalizedCity = capitalize(city);
        const capitalizedCountry = country ? capitalize(country) : '';

        const prayerTimesContainer = document.getElementById('prayer-times');
        prayerTimesContainer.innerHTML = `
            <h2>Prayer Times for ${capitalizedCity}${capitalizedCountry ? ', ' + capitalizedCountry : ''}:</h2>
            <div class="prayer-card">
                <h3>${capitalizedCity}${capitalizedCountry ? ', ' + capitalizedCountry : ''}:</h3>
                <p>Date: ${currentDate}</p>
                <p>Current Time: ${currentTime}</p>
                <ul>
                    <li><strong>Fajr:</strong> ${convertTo12HourFormat(prayerTimes.Fajr)}</li>
                    <li><strong>Dhuhr:</strong> ${convertTo12HourFormat(prayerTimes.Dhuhr)}</li>
                    <li><strong>Asr:</strong> ${convertTo12HourFormat(prayerTimes.Asr)}</li>
                    <li><strong>Maghrib:</strong> ${convertTo12HourFormat(prayerTimes.Maghrib)}</li>
                    <li><strong>Isha:</strong> ${convertTo12HourFormat(prayerTimes.Isha)}</li>
                </ul>
            </div>
        `;
    }

    function convertTo12HourFormat(time) {
        const [hours, minutes] = time.split(':');
        const hoursInt = parseInt(hours);
        const period = hoursInt >= 12 ? 'PM' : 'AM';
        const hours12 = hoursInt % 12 || 12;
        return `${hours12}:${minutes} ${period}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const city = urlParams.get('page');
        if (city) {
            const cityName = city.split('-prayer-times').map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
            
            document.getElementById('search_query').value = cityName;
            fetchPrayerTimes(cityName);
        }
    });
</script>
</body>
</html>
