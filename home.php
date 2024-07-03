<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWOT - Personal Work Off Tracker</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f0f0;
            margin: 20px;
        }
        .container {
            margin-top: 20px;
        }
        .form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        table {
            margin-top: 20px;
        }
        th, td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <form action="" method="post">
                <div class="form-group">
                    <label for="arrived_at">Kelgan vaqti tanlang</label>
                    <input type="datetime-local" id="arrived_at" name="arrived_at" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="leaved_at">Ketgan vaqti tanlang</label>
                    <input type="datetime-local" id="leaved_at" name="leaved_at" class="form-control" required>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" id="worked_off" name="worked_off" value="1" class="form-check-input">
                    <label for="worked_off" class="form-check-label">Ish soati qarzi</label>
                </div>
                <button type="submit" class="btn btn-primary">Jo'natish</button>
            </form>
        </div>

        <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $tracker = new WorkOffTracker(
                    'mysql:host=localhost;dbname=work_off_tracker',
                    'beko',
                    '9999'
                );

                $message = $tracker->saveTimes($_POST['arrived_at'], $_POST['leaved_at'], isset($_POST['worked_off']));
                echo '<div class="alert alert-info mt-3">' . $message . '</div>';
            }
        ?>

        <?php
            $tracker = new WorkOffTracker(
                'mysql:host=localhost;dbname=work_off_tracker',
                'beko',
                '9999'
            );

            $records = $tracker->fetchAllRecords();
            $totalWorkOffHours = $tracker->calculateTotalWorkOffHours();
        ?>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Arrived at</th>
                    <th>Leaved at</th>
                    <th>Required work off</th>
                    <th>Worked off</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $index => $record): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo $record['arrived_at']; ?></td>
                    <td><?php echo $record['leaved_at']; ?></td>
                    <td><?php echo floor($record['required_work_off'] / 60) . ' hours ' . ($record['required_work_off'] % 60) . ' min'; ?></td>
                    <td><input type="checkbox" <?php echo $record['worked_off'] ? 'checked' : ''; ?> disabled></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4"><strong>Total work off hours</strong></td>
                    <td><strong><?php echo floor($totalWorkOffHours / 60) . ' hours ' . ($totalWorkOffHours % 60) . ' min'; ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php

class WorkOffTracker
{

    private $pdo;

    public function __construct($dsn, $username, $password)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Xatolik: " . $e->getMessage());
        }
    }

    public function saveTimes($arrived_at, $leaved_at, $worked_off)
    {
        if (!empty($arrived_at) && !empty($leaved_at)) {
            $arrived_at = new DateTime($arrived_at);
            $leaved_at  = new DateTime($leaved_at);
            $interval = $arrived_at->diff($leaved_at);
            $work_duration = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
            $required_work_off = 540 - $work_duration;

            $arrived_at = $arrived_at->format('Y-m-d H:i:s');
            $leaved_at = $leaved_at->format('Y-m-d H:i:s');

            $query = "INSERT INTO daily (arrived_at, leaved_at, work_duration, required_work_off, worked_off) VALUES (:arrived_at, :leaved_at, :work_duration, :required_work_off, :worked_off)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':arrived_at', $arrived_at);
            $stmt->bindParam(':leaved_at', $leaved_at);
            $stmt->bindParam(':work_duration', $work_duration);
            $stmt->bindParam(':required_work_off', $required_work_off);
            $stmt->bindParam(':worked_off', $worked_off, PDO::PARAM_BOOL);
            $stmt->execute();

            return "Ma'lumotlar muvaffaqiyatli saqlandi.";
        } else {
            return 'Iltimos, barcha maydonlarni to\'ldiring.';
        }
    }

    public function fetchAllRecords()
    {
        $query = "SELECT * FROM daily";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calculateTotalWorkOffHours()
    {
        $query = "SELECT SUM(required_work_off) as total_work_off_hours FROM daily";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_work_off_hours'];
    }
}

?>