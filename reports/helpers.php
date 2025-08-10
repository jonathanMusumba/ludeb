<?php
function getTotalSchools($conn) {
    $query = "SELECT COUNT(*) AS total FROM schools";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    return $data['total'];
}

function getActiveSchools($conn) {
    $query = "SELECT COUNT(*) AS total FROM schools WHERE status = 'Active'";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    return $data['total'];
}

function getTotalCandidates($conn) {
    $query = "SELECT COUNT(*) AS total FROM candidates";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    return $data['total'];
}

function getMaleCandidates($conn) {
    $query = "SELECT COUNT(*) AS total FROM candidates WHERE gender = 'M'";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    return $data['total'];
}

function getFemaleCandidates($conn) {
    $query = "SELECT COUNT(*) AS total FROM candidates WHERE gender = 'F'";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    return $data['total'];
}

// Add more functions for fetching report data as needed
?>
