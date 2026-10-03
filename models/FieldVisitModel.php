<?php

class FieldVisitModel {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    private function scope(array $user, string $alias = 'fv'): array {
        $role = strtoupper((string)($user['role'] ?? 'STAFF'));
        $hospitalId = (int)($user['hospital_id'] ?? 0);
        $userId = (int)($user['id'] ?? 0);

        if (in_array($role, ['SUPERADMIN', 'ADMIN'], true)) {
            return ['sql' => '1=1', 'params' => []];
        }

        if (in_array($role, ['DIRECTOR', 'SCHEDULER'], true)) {
            return [
                'sql' => "{$alias}.hospital_id = :scope_hospital",
                'params' => [':scope_hospital' => $hospitalId],
            ];
        }

        return [
            'sql' => "{$alias}.hospital_id = :scope_hospital AND {$alias}.created_by = :scope_user",
            'params' => [
                ':scope_hospital' => $hospitalId,
                ':scope_user' => $userId,
            ],
        ];
    }

    public function createVisit(array $data): int {
        $sql = "INSERT INTO field_visits (
                    hospital_id, created_by, visit_date, patient_ref, patient_name, patient_age,
                    visit_type, chief_concern, systolic, diastolic, pulse, temperature, spo2,
                    weight, height, symptoms, assessment, care_plan, risk_level,
                    follow_up_date, follow_up_status, referral_required, referral_note,
                    latitude, longitude, accuracy_m, address_note, photo_consent, status, created_at, updated_at
                ) VALUES (
                    :hospital_id, :created_by, :visit_date, :patient_ref, :patient_name, :patient_age,
                    :visit_type, :chief_concern, :systolic, :diastolic, :pulse, :temperature, :spo2,
                    :weight, :height, :symptoms, :assessment, :care_plan, :risk_level,
                    :follow_up_date, :follow_up_status, :referral_required, :referral_note,
                    :latitude, :longitude, :accuracy_m, :address_note, :photo_consent, :status, NOW(), NOW()
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':hospital_id' => $data['hospital_id'],
            ':created_by' => $data['created_by'],
            ':visit_date' => $data['visit_date'],
            ':patient_ref' => $data['patient_ref'],
            ':patient_name' => $data['patient_name'],
            ':patient_age' => $data['patient_age'],
            ':visit_type' => $data['visit_type'],
            ':chief_concern' => $data['chief_concern'],
            ':systolic' => $data['systolic'],
            ':diastolic' => $data['diastolic'],
            ':pulse' => $data['pulse'],
            ':temperature' => $data['temperature'],
            ':spo2' => $data['spo2'],
            ':weight' => $data['weight'],
            ':height' => $data['height'],
            ':symptoms' => $data['symptoms'],
            ':assessment' => $data['assessment'],
            ':care_plan' => $data['care_plan'],
            ':risk_level' => $data['risk_level'],
            ':follow_up_date' => $data['follow_up_date'],
            ':follow_up_status' => $data['follow_up_status'],
            ':referral_required' => $data['referral_required'],
            ':referral_note' => $data['referral_note'],
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':accuracy_m' => $data['accuracy_m'],
            ':address_note' => $data['address_note'],
            ':photo_consent' => $data['photo_consent'],
            ':status' => $data['status'],
        ]);

        return (int)$this->conn->lastInsertId();
    }

    public function addPhoto(int $visitId, string $storedPath, string $originalName, string $mimeType, int $fileSize): int {
        $stmt = $this->conn->prepare(
            "INSERT INTO field_visit_photos
             (field_visit_id, stored_path, original_name, mime_type, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$visitId, $storedPath, $originalName, $mimeType, $fileSize]);
        return (int)$this->conn->lastInsertId();
    }

    public function getVisibleVisits(array $user, array $filters = [], int $limit = 100): array {
        $scope = $this->scope($user, 'fv');
        $where = [$scope['sql']];
        $params = $scope['params'];

        if (!empty($filters['status']) && in_array($filters['status'], ['DRAFT', 'COMPLETED'], true)) {
            $where[] = 'fv.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['risk_level']) && in_array($filters['risk_level'], ['ROUTINE', 'WATCH', 'HIGH', 'URGENT'], true)) {
            $where[] = 'fv.risk_level = :risk_level';
            $params[':risk_level'] = $filters['risk_level'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'fv.visit_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'fv.visit_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(fv.patient_ref LIKE :q OR fv.patient_name LIKE :q OR fv.chief_concern LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $limit = max(1, min(5000, $limit));
        $sql = "SELECT
                    fv.*,
                    h.name AS hospital_name,
                    u.name AS created_by_name,
                    (SELECT COUNT(*) FROM field_visit_photos p WHERE p.field_visit_id = fv.id) AS photo_count,
                    (SELECT MIN(p2.id) FROM field_visit_photos p2 WHERE p2.field_visit_id = fv.id) AS first_photo_id
                FROM field_visits fv
                LEFT JOIN hospitals h ON h.id = fv.hospital_id
                LEFT JOIN users u ON u.id = fv.created_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY fv.visit_date DESC, fv.id DESC
                LIMIT {$limit}";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(array $user): array {
        $scope = $this->scope($user, 'fv');
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN fv.visit_date = CURDATE() THEN 1 ELSE 0 END) AS today_count,
                    SUM(CASE WHEN fv.status = 'DRAFT' THEN 1 ELSE 0 END) AS draft_count,
                    SUM(CASE WHEN fv.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
                    SUM(CASE WHEN fv.risk_level IN ('HIGH','URGENT') THEN 1 ELSE 0 END) AS high_risk_count,
                    SUM(CASE
                          WHEN fv.follow_up_status = 'PENDING'
                           AND fv.follow_up_date IS NOT NULL
                           AND fv.follow_up_date <= CURDATE()
                          THEN 1 ELSE 0 END) AS followup_due_count
                FROM field_visits fv
                WHERE {$scope['sql']}";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($scope['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'today_count' => (int)($row['today_count'] ?? 0),
            'draft_count' => (int)($row['draft_count'] ?? 0),
            'completed_count' => (int)($row['completed_count'] ?? 0),
            'high_risk_count' => (int)($row['high_risk_count'] ?? 0),
            'followup_due_count' => (int)($row['followup_due_count'] ?? 0),
        ];
    }

    public function getVisibleVisitById(int $visitId, array $user): ?array {
        $scope = $this->scope($user, 'fv');
        $sql = "SELECT fv.*, h.name AS hospital_name, u.name AS created_by_name
                FROM field_visits fv
                LEFT JOIN hospitals h ON h.id = fv.hospital_id
                LEFT JOIN users u ON u.id = fv.created_by
                WHERE fv.id = :visit_id AND {$scope['sql']}
                LIMIT 1";

        $params = $scope['params'];
        $params[':visit_id'] = $visitId;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateDraft(int $visitId, array $data, array $user): bool {
        $existing = $this->getVisibleVisitById($visitId, $user);
        if (!$existing || strtoupper((string)$existing['status']) !== 'DRAFT') {
            return false;
        }

        $sql = "UPDATE field_visits
                SET visit_date = :visit_date,
                    patient_ref = :patient_ref,
                    patient_name = :patient_name,
                    patient_age = :patient_age,
                    visit_type = :visit_type,
                    chief_concern = :chief_concern,
                    systolic = :systolic,
                    diastolic = :diastolic,
                    pulse = :pulse,
                    temperature = :temperature,
                    spo2 = :spo2,
                    weight = :weight,
                    height = :height,
                    symptoms = :symptoms,
                    assessment = :assessment,
                    care_plan = :care_plan,
                    risk_level = :risk_level,
                    follow_up_date = :follow_up_date,
                    follow_up_status = :follow_up_status,
                    referral_required = :referral_required,
                    referral_note = :referral_note,
                    latitude = :latitude,
                    longitude = :longitude,
                    accuracy_m = :accuracy_m,
                    address_note = :address_note,
                    photo_consent = :photo_consent,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :visit_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':visit_date' => $data['visit_date'],
            ':patient_ref' => $data['patient_ref'],
            ':patient_name' => $data['patient_name'],
            ':patient_age' => $data['patient_age'],
            ':visit_type' => $data['visit_type'],
            ':chief_concern' => $data['chief_concern'],
            ':systolic' => $data['systolic'],
            ':diastolic' => $data['diastolic'],
            ':pulse' => $data['pulse'],
            ':temperature' => $data['temperature'],
            ':spo2' => $data['spo2'],
            ':weight' => $data['weight'],
            ':height' => $data['height'],
            ':symptoms' => $data['symptoms'],
            ':assessment' => $data['assessment'],
            ':care_plan' => $data['care_plan'],
            ':risk_level' => $data['risk_level'],
            ':follow_up_date' => $data['follow_up_date'],
            ':follow_up_status' => $data['follow_up_status'],
            ':referral_required' => $data['referral_required'],
            ':referral_note' => $data['referral_note'],
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':accuracy_m' => $data['accuracy_m'],
            ':address_note' => $data['address_note'],
            ':photo_consent' => $data['photo_consent'],
            ':status' => $data['status'],
            ':visit_id' => $visitId,
        ]);
    }

    public function markFollowUpDone(int $visitId, array $user): bool {
        $scope = $this->scope($user, 'fv');
        $sql = "UPDATE field_visits fv
                SET fv.follow_up_status = 'DONE', fv.updated_at = NOW()
                WHERE fv.id = :visit_id
                  AND fv.follow_up_status = 'PENDING'
                  AND {$scope['sql']}";

        $params = $scope['params'];
        $params[':visit_id'] = $visitId;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function getPhotoVisible(int $photoId, array $user): ?array {
        $scope = $this->scope($user, 'fv');
        $sql = "SELECT p.*, fv.hospital_id, fv.created_by
                FROM field_visit_photos p
                INNER JOIN field_visits fv ON fv.id = p.field_visit_id
                WHERE p.id = :photo_id AND {$scope['sql']}
                LIMIT 1";

        $params = $scope['params'];
        $params[':photo_id'] = $photoId;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
