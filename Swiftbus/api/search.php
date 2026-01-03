<?php
/**
 * SwiftBus Search API
 * 
 * Handles bus search, route information, and schedule queries
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'search_buses':
        handleSearchBuses();
        break;
    case 'get_cities':
        handleGetCities();
        break;
    case 'get_routes':
        handleGetRoutes();
        break;
    case 'get_schedule_details':
        handleGetScheduleDetails();
        break;
    case 'get_available_seats':
        handleGetAvailableSeats();
        break;
    case 'get_popular_routes':
        handleGetPopularRoutes();
        break;
    default:
        handleError('Invalid action', 400);
}

/**
 * Handle bus search
 */
function handleSearchBuses() {
    $input = $_GET;
    
    // Validate required parameters
    $required = ['from', 'to', 'date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Parameter '$field' is required", 400);
        }
    }
    
    $fromCity = sanitizeInput($input['from']);
    $toCity = sanitizeInput($input['to']);
    $travelDate = sanitizeInput($input['date']);
    $passengers = (int)($input['passengers'] ?? 1);
    
    // Validate date
    if (!strtotime($travelDate) || $travelDate < date('Y-m-d')) {
        handleError('Invalid or past travel date', 400);
    }
    
    // Validate passenger count
    if ($passengers < 1 || $passengers > 10) {
        handleError('Passenger count must be between 1 and 10', 400);
    }
    
    try {
        $db = getDB();
        
        // Get day of week for the travel date
        $dayOfWeek = strtolower(date('l', strtotime($travelDate)));
        
        // Search for available buses
        $stmt = $db->prepare("
            SELECT 
                s.id as schedule_id,
                s.schedule_id,
                s.departure_time,
                s.arrival_time,
                s.price,
                s.days_of_week,
                b.bus_id,
                b.bus_number,
                b.bus_type,
                b.total_seats,
                b.amenities,
                comp.company_id,
                comp.name as company_name,
                comp.rating,
                r.route_id,
                r.distance_km,
                r.estimated_duration_hours,
                oc.name as origin_city,
                oc.city_code as origin_code,
                dc.name as destination_city,
                dc.city_code as destination_code
            FROM schedules s
            JOIN buses b ON s.bus_id = b.id
            JOIN bus_companies comp ON b.company_id = comp.id
            JOIN routes r ON s.route_id = r.id
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            WHERE oc.city_code = ? 
            AND dc.city_code = ?
            AND s.is_active = 1
            AND b.status = 'active'
            AND comp.is_active = 1
            AND (s.effective_from <= ? AND (s.effective_until IS NULL OR s.effective_until >= ?))
        ");
        
        $stmt->execute([$fromCity, $toCity, $travelDate, $travelDate]);
        $schedules = $stmt->fetchAll();
        
        $results = [];
        
        foreach ($schedules as $schedule) {
            // Check if this schedule runs on the requested day
            $daysOfWeek = json_decode($schedule['days_of_week'], true);
            if (!in_array($dayOfWeek, $daysOfWeek)) {
                continue;
            }
            
            // Get available seats for this schedule and date
            $availableSeats = getAvailableSeats($schedule['schedule_id'], $travelDate);
            $availableSeatCount = count($availableSeats);
            
            // Skip if not enough seats available
            if ($availableSeatCount < $passengers) {
                continue;
            }
            
            // Parse amenities
            $amenities = json_decode($schedule['amenities'], true) ?: [];
            
            // Calculate estimated arrival time
            $departureTime = $schedule['departure_time'];
            $duration = $schedule['estimated_duration_hours'];
            $arrivalTime = date('H:i:s', strtotime($departureTime) + ($duration * 3600));
            
            $results[] = [
                'id' => $schedule['schedule_id'],
                'schedule_id' => $schedule['schedule_id'],
                'company' => [
                    'id' => $schedule['company_id'],
                    'name' => $schedule['company_name'],
                    'rating' => (float)$schedule['rating']
                ],
                'bus' => [
                    'id' => $schedule['bus_id'],
                    'number' => $schedule['bus_number'],
                    'type' => $schedule['bus_type'],
                    'total_seats' => (int)$schedule['total_seats'],
                    'amenities' => $amenities
                ],
                'route' => [
                    'id' => $schedule['route_id'],
                    'origin' => [
                        'code' => $schedule['origin_code'],
                        'name' => $schedule['origin_city']
                    ],
                    'destination' => [
                        'code' => $schedule['destination_code'],
                        'name' => $schedule['destination_city']
                    ],
                    'distance_km' => (int)$schedule['distance_km'],
                    'duration_hours' => (float)$schedule['estimated_duration_hours']
                ],
                'schedule' => [
                    'departure_time' => $departureTime,
                    'arrival_time' => $arrivalTime,
                    'travel_date' => $travelDate
                ],
                'pricing' => [
                    'base_price' => (float)$schedule['price'],
                    'total_price' => (float)$schedule['price'] * $passengers,
                    'currency' => 'ETB'
                ],
                'availability' => [
                    'available_seats' => $availableSeatCount,
                    'seats_needed' => $passengers,
                    'is_available' => $availableSeatCount >= $passengers
                ]
            ];
        }
        
        // Sort results by departure time
        usort($results, function($a, $b) {
            return strcmp($a['schedule']['departure_time'], $b['schedule']['departure_time']);
        });
        
        handleSuccess([
            'buses' => $results,
            'search_params' => [
                'from' => $fromCity,
                'to' => $toCity,
                'date' => $travelDate,
                'passengers' => $passengers
            ],
            'total_results' => count($results)
        ]);
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        handleError('Search failed. Please try again.', 500);
    }
}

/**
 * Get all cities
 */
function handleGetCities() {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT city_code, name, region, latitude, longitude 
            FROM cities 
            WHERE is_active = 1 
            ORDER BY name
        ");
        $stmt->execute();
        $cities = $stmt->fetchAll();
        
        handleSuccess($cities);
        
    } catch (Exception $e) {
        error_log("Get cities error: " . $e->getMessage());
        handleError('Failed to fetch cities', 500);
    }
}

/**
 * Get all routes
 */
function handleGetRoutes() {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                r.route_id,
                r.distance_km,
                r.estimated_duration_hours,
                r.base_price,
                oc.name as origin_city,
                oc.city_code as origin_code,
                dc.name as destination_city,
                dc.city_code as destination_code
            FROM routes r
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            WHERE r.is_active = 1
            ORDER BY oc.name, dc.name
        ");
        $stmt->execute();
        $routes = $stmt->fetchAll();
        
        $formattedRoutes = [];
        foreach ($routes as $route) {
            $formattedRoutes[] = [
                'id' => $route['route_id'],
                'origin' => [
                    'code' => $route['origin_code'],
                    'name' => $route['origin_city']
                ],
                'destination' => [
                    'code' => $route['destination_code'],
                    'name' => $route['destination_city']
                ],
                'distance_km' => (int)$route['distance_km'],
                'duration_hours' => (float)$route['estimated_duration_hours'],
                'base_price' => (float)$route['base_price']
            ];
        }
        
        handleSuccess($formattedRoutes);
        
    } catch (Exception $e) {
        error_log("Get routes error: " . $e->getMessage());
        handleError('Failed to fetch routes', 500);
    }
}

/**
 * Get schedule details
 */
function handleGetScheduleDetails() {
    if (empty($_GET['schedule_id'])) {
        handleError('Schedule ID is required', 400);
    }
    
    $scheduleId = sanitizeInput($_GET['schedule_id']);
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                s.*,
                b.bus_number,
                b.bus_type,
                b.total_seats,
                b.amenities,
                comp.name as company_name,
                comp.rating,
                r.distance_km,
                r.estimated_duration_hours,
                oc.name as origin_city,
                oc.city_code as origin_code,
                dc.name as destination_city,
                dc.city_code as destination_code
            FROM schedules s
            JOIN buses b ON s.bus_id = b.id
            JOIN bus_companies comp ON b.company_id = comp.id
            JOIN routes r ON s.route_id = r.id
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            WHERE s.schedule_id = ?
        ");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            handleError('Schedule not found', 404);
        }
        
        $amenities = json_decode($schedule['amenities'], true) ?: [];
        $daysOfWeek = json_decode($schedule['days_of_week'], true) ?: [];
        
        $result = [
            'id' => $schedule['schedule_id'],
            'company' => [
                'name' => $schedule['company_name'],
                'rating' => (float)$schedule['rating']
            ],
            'bus' => [
                'number' => $schedule['bus_number'],
                'type' => $schedule['bus_type'],
                'total_seats' => (int)$schedule['total_seats'],
                'amenities' => $amenities
            ],
            'route' => [
                'origin' => [
                    'code' => $schedule['origin_code'],
                    'name' => $schedule['origin_city']
                ],
                'destination' => [
                    'code' => $schedule['destination_code'],
                    'name' => $schedule['destination_city']
                ],
                'distance_km' => (int)$schedule['distance_km'],
                'duration_hours' => (float)$schedule['estimated_duration_hours']
            ],
            'schedule' => [
                'departure_time' => $schedule['departure_time'],
                'arrival_time' => $schedule['arrival_time'],
                'days_of_week' => $daysOfWeek,
                'price' => (float)$schedule['price']
            ]
        ];
        
        handleSuccess($result);
        
    } catch (Exception $e) {
        error_log("Get schedule details error: " . $e->getMessage());
        handleError('Failed to fetch schedule details', 500);
    }
}

/**
 * Get available seats for a schedule and date
 */
function handleGetAvailableSeats() {
    if (empty($_GET['schedule_id']) || empty($_GET['date'])) {
        handleError('Schedule ID and date are required', 400);
    }
    
    $scheduleId = sanitizeInput($_GET['schedule_id']);
    $travelDate = sanitizeInput($_GET['date']);
    
    // Validate date
    if (!strtotime($travelDate) || $travelDate < date('Y-m-d')) {
        handleError('Invalid or past travel date', 400);
    }
    
    try {
        $db = getDB();
        
        // Get schedule info
        $stmt = $db->prepare("SELECT id FROM schedules WHERE schedule_id = ?");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            handleError('Schedule not found', 404);
        }
        
        $availableSeats = getAvailableSeats($schedule['id'], $travelDate);
        
        handleSuccess([
            'available_seats' => $availableSeats,
            'total_available' => count($availableSeats),
            'schedule_id' => $scheduleId,
            'date' => $travelDate
        ]);
        
    } catch (Exception $e) {
        error_log("Get available seats error: " . $e->getMessage());
        handleError('Failed to fetch available seats', 500);
    }
}

/**
 * Get popular routes
 */
function handleGetPopularRoutes() {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                r.route_id,
                oc.name as origin_city,
                oc.city_code as origin_code,
                dc.name as destination_city,
                dc.city_code as destination_code,
                r.base_price,
                r.estimated_duration_hours,
                COUNT(b.id) as booking_count
            FROM routes r
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            LEFT JOIN schedules s ON r.id = s.route_id
            LEFT JOIN bookings b ON s.id = b.schedule_id AND b.booking_status IN ('confirmed', 'completed')
            WHERE r.is_active = 1
            GROUP BY r.id
            ORDER BY booking_count DESC, r.base_price ASC
            LIMIT 10
        ");
        $stmt->execute();
        $routes = $stmt->fetchAll();
        
        $popularRoutes = [];
        foreach ($routes as $route) {
            $popularRoutes[] = [
                'id' => $route['route_id'],
                'origin' => [
                    'code' => $route['origin_code'],
                    'name' => $route['origin_city']
                ],
                'destination' => [
                    'code' => $route['destination_code'],
                    'name' => $route['destination_city']
                ],
                'base_price' => (float)$route['base_price'],
                'duration_hours' => (float)$route['estimated_duration_hours'],
                'booking_count' => (int)$route['booking_count']
            ];
        }
        
        handleSuccess($popularRoutes);
        
    } catch (Exception $e) {
        error_log("Get popular routes error: " . $e->getMessage());
        handleError('Failed to fetch popular routes', 500);
    }
}
?>