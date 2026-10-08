<?php
/** Common four-type official-style leave form support; PHP 8.0 compatible. */
final class LeaveOfficialFormService {
    private PDO $db;
    public function __construct(PDO $db) {$this->db=$db;}
    public static function categories(): array {
        return ['ลาป่วย','ลากิจส่วนตัว','ลาพักผ่อน','ลาคลอดบุตร'];
    }
    public static function supports(string $name): bool {
        return in_array(trim($name),self::categories(),true);
    }
    public function schemaReady(): bool {
        try {return (bool)$this->db->query("SHOW TABLES LIKE 'leave_official_form_details'")->fetchColumn();}
        catch(Throwable $e){return false;}
    }
    public static function clean(array $data): array {
        $limits=['contact_address'=>500,'contact_phone'=>40,'delegate_name'=>180,
                 'delegate_position'=>180,'delegate_duties'=>500];
        $out=[];
        foreach($limits as $k=>$max){
            $val=$data[$k]??'';
            if(!is_string($val))throw new InvalidArgumentException('ชนิดข้อมูลใบลาไม่ถูกต้อง');
            $val=trim($val);
            if(mb_strlen($val,'UTF-8')>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$val))
                throw new InvalidArgumentException('ข้อมูลในช่อง '.$k.' ไม่ถูกต้อง');
            $out[$k]=$val;
        }
        if($out['contact_phone']!==''&&!preg_match('/^[0-9+(). \-]{6,40}$/D',$out['contact_phone']))
            throw new InvalidArgumentException('เบอร์ติดต่อไม่ถูกต้อง');
        return $out;
    }
    /** Must be called within a transaction that creates the leave request. */
    public function insert(int $id,array $data): void {
        $d=self::clean($data);
        $stmt=$this->db->prepare('INSERT INTO leave_official_form_details
            (leave_request_id,contact_address,contact_phone,delegate_name,delegate_position,delegate_duties)
            VALUES (?,?,?,?,?,?)');
        $stmt->execute([$id,$d['contact_address']?:null,$d['contact_phone']?:null,
            $d['delegate_name']?:null,$d['delegate_position']?:null,$d['delegate_duties']?:null]);
    }
    public function find(int $id): array {
        if(!$this->schemaReady())return [];
        $stmt=$this->db->prepare("SELECT d.*,reviewer.name reviewer_name,reviewer.position reviewer_position,
            supervisor.name supervisor_name,supervisor.position supervisor_position
            FROM leave_official_form_details d
            LEFT JOIN users reviewer ON reviewer.id=d.hr_reviewed_by
            LEFT JOIN users supervisor ON supervisor.id=d.supervisor_opinion_by
            WHERE d.leave_request_id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:[];
    }
    /** Local managers can review only their hospital, global admins any facility. */
    public function review(int $id,int $userId,int $hospitalId,bool $global,string $note): void {
        if(!$this->schemaReady())throw new RuntimeException('ยังไม่ได้ติดตั้งตารางใบลาราชการ');
        $note=trim($note);
        if(mb_strlen($note,'UTF-8')>500)throw new InvalidArgumentException('ข้อสังเกตยาวเกินกำหนด');
        $stmt=$this->db->prepare('SELECT lr.status,u.hospital_id
          FROM leave_requests lr JOIN users u ON lr.user_id=u.id WHERE lr.id=? FOR UPDATE');
        $stmt->execute([$id]);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$r||(!$global&&(int)$r['hospital_id']!==$hospitalId))
            throw new RuntimeException('ไม่มีสิทธิ์ตรวจสอบใบลานี้');
        if($r['status']!=='PENDING')throw new RuntimeException('ตรวจสอบได้เฉพาะใบลารอพิจารณา');
        $stmt=$this->db->prepare('INSERT INTO leave_official_form_details
          (leave_request_id,hr_review_note,hr_reviewed_by,hr_reviewed_at)
          VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE
          hr_review_note=VALUES(hr_review_note),hr_reviewed_by=VALUES(hr_reviewed_by),hr_reviewed_at=NOW()');
        $stmt->execute([$id,$note?:null,$userId]);
    }
    /** Called inside the locked approval transaction; empty = no opinion recorded. */
    public function saveOpinion(int $id,int $userId,string $opinion): void {
        $opinion=trim($opinion);
        if(mb_strlen($opinion,'UTF-8')>500)throw new InvalidArgumentException('ความเห็นเกิน 500 ตัวอักษร');
        if($opinion===''||!$this->schemaReady())return;
        $stmt=$this->db->prepare('INSERT INTO leave_official_form_details
          (leave_request_id,supervisor_opinion,supervisor_opinion_by,supervisor_opinion_at)
          VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE
          supervisor_opinion=VALUES(supervisor_opinion),
          supervisor_opinion_by=VALUES(supervisor_opinion_by),supervisor_opinion_at=NOW()');
        $stmt->execute([$id,$opinion,$userId]);
    }
    /** Saved leave-request days follow the configured leave-type day-counting rule. */
    public function summary(array $leave): array {
        $start=new DateTimeImmutable((string)$leave['start_date']);
        $y=(int)$start->format('Y');
        $begin=((int)$start->format('n')>=10)?$y:$y-1;
        $from=sprintf('%04d-10-01',$begin);$until=sprintf('%04d-10-01',$begin+1);
        $sql='SELECT q.leave_type,COALESCE(SUM(lr.num_days),0) days
          FROM leave_requests lr JOIN leave_quotas q ON q.id=lr.leave_type_id
          WHERE lr.user_id=? AND lr.id<>? AND lr.status=? AND
          lr.start_date>=? AND lr.start_date<? AND lr.end_date<?
          GROUP BY q.id,q.leave_type';
        $stmt=$this->db->prepare($sql);
        $stmt->execute([(int)$leave['user_id'],(int)$leave['id'],'APPROVED',
            $from,$until,$start->format('Y-m-d')]);
        $prior=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)$prior[$r['leave_type']]=(float)$r['days'];
        $rows=[];foreach(self::categories() as $name){
            $p=round($prior[$name]??0,2);
            $current=$name===(string)$leave['leave_type']?(float)$leave['num_days']:0.0;
            $rows[]=['name'=>$name,'previous'=>$p,'current'=>$current,'total'=>round($p+$current,2)];
        }
        return ['fiscal_year_be'=>$begin+544,'rows'=>$rows];
    }
}
