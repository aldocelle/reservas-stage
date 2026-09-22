<?php require __DIR__.'/config.php';
if($_SERVER['REQUEST_METHOD']!=='POST') respond(['error'=>'Método no permitido'],405);
$x=body();foreach(['date','slotId','firstName','lastName','whatsapp'] as $k)if(empty(trim((string)($x[$k]??''))) )respond(['error'=>'Faltan datos obligatorios'],422);
$pdo->beginTransaction();
try{
 $q=$pdo->prepare("SELECT ts.id,ts.capacity,st.weekday FROM time_slots ts JOIN schedule_templates st ON st.id=ts.template_id WHERE ts.id=? AND ts.active=1 AND st.active=1 FOR UPDATE");$q->execute([$x['slotId']]);$slot=$q->fetch();
 if(!$slot)throw new Exception('Horario no disponible');
 $count=$pdo->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_date=? AND slot_id=? AND status IN ('confirmed','attended')");$count->execute([$x['date'],$x['slotId']]);
 if((int)$count->fetchColumn()>=(int)$slot['capacity'])throw new Exception('No quedan cupos disponibles');
 $code='VS-'.strtoupper(bin2hex(random_bytes(4)));
 $ins=$pdo->prepare("INSERT INTO reservations(reservation_code,reservation_date,slot_id,first_name,last_name,whatsapp,email,whatsapp_consent) VALUES(?,?,?,?,?,?,?,?)");
 $ins->execute([$code,$x['date'],$x['slotId'],trim($x['firstName']),trim($x['lastName']),trim($x['whatsapp']),trim($x['email']??'')?:null,!empty($x['whatsappConsent'])?1:0]);
 $pdo->commit();respond(['reservation'=>['id'=>$pdo->lastInsertId(),'reservation_code'=>$code,'status'=>'confirmed'] ],201);
}catch(Throwable $e){$pdo->rollBack();respond(['error'=>$e->getMessage()],409);}
