<?php 

class pay {

	function NewPdo ($config) {
		$pdo = new PDO(
			sprintf(
				'mysql:host=%s;dbname=%s;port=%s;charset=%s',
				$config['host'],
				$config['dbname'],
				$config['port'],
				$config['charset']), 
			$config['user'], 
			$config['pass']
			);
		return $pdo;
	}

	function Filter() {
		if ($_POST) {
			$filter['invoiceid'] = filter_input(INPUT_POST, 'invoiceid');
			$filter['amount'] = filter_input(INPUT_POST, 'amount');
			$filter['userid'] = filter_input(INPUT_POST, 'userid');
			$filter['return_url'] = filter_input(INPUT_POST, 'return_url');
			$filter['callback_url'] = filter_input(INPUT_POST, 'callback_url');
			$filter['tool'] = filter_input(INPUT_POST, 'tool'); //wechat or alipay
			$filter['companyname'] = filter_input(INPUT_POST, 'companyname');
			$filter['secretKey'] = filter_input(INPUT_POST, 'secretKey');
		}
		elseif ($_SESSION) {
			$filter['invoiceid'] = $_SESSION['invoiceid'];
			$filter['amount'] = $_SESSION['amount'];
			$filter['userid'] = $_SESSION['userid'];
			$filter['notify'] = $_SESSION['notify'];
			$filter['tool'] = $_SESSION['tool'];
		}
		return $filter;
	}

	function CheckTransaction($invoiceid,$pdo) {  //查找存在进行中的交易
		$sql = 'SELECT qrid FROM transaction WHERE invoiceid = :invoiceid AND status = 0';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':invoiceid',$invoiceid);
		$statement->execute();
		$result = $statement->fetch(PDO::FETCH_ASSOC);
		return $result['qrid'];
	}

	function GetQR($qrid,$pdo) {
		$sql = 'SELECT dir,status FROM qrcode WHERE id = :qrid';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':qrid',$qrid);
		$statement->execute();
		$result = $statement->fetch(PDO::FETCH_ASSOC);
		return $result;
	}

	function UpdateExpir($qrid,$invoiceid,$pdo) {
		$datetime = new DateTime();
		$interval = new DateInterval('PT25M');
		$datetime->add($interval);
		$expirtime = $datetime->format('Y-m-d H:i:s');

		$sql = 'UPDATE qrcode SET expir = :expir WHERE id = :id';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':id',$qrid);
		$statement->bindValue(':expir',$expirtime);
		$statement->execute();

		$sql = 'UPDATE transaction SET endtime = :endtime WHERE invoiceid = :invoiceid AND status = 0';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':invoiceid',$invoiceid);
		$statement->bindValue(':endtime',$expirtime);
		$statement->execute();
	}

	function NewTransaction($pdo,$filter) {
		$datetime = new DateTime();
		$createtime = $datetime->format('Y-m-d H:i:s');
		$interval = new DateInterval('PT25M');
		$datetime->add($interval);
		$expirtime = $datetime->format('Y-m-d H:i:s');

		$sql = 'SELECT id,dir FROM qrcode WHERE locked = 0 AND amount = :amount';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':amount',$filter['amount']);
		$statement->execute();
		$qrcode = $statement->fetch(PDO::FETCH_ASSOC);

		$sql = 'INSERT INTO transaction (userid,invoiceid,qrid,createtime,endtime) VALUES (:userid,:invoiceid,:qrid,:createtime,:endtime)';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':userid',$filter['userid']);
		$statement->bindValue(':invoiceid',$filter['invoiceid']);
		$statement->bindValue(':qrid',$qrcode['id']);
		$statement->bindValue(':createtime',$createtime);
		$statement->bindValue(':endtime',$expirtime);
		$statement->execute();

		$sql = 'UPDATE qrcode SET expir = :expir,locked = 1 WHERE id = :id';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':id',$qrcode['id']);
		$statement->bindValue(':expir',$expirtime);
		$statement->execute();

		$_SESSION['invoiceid'] = $filter['invoiceid'];
		$_SESSION['amount'] = $filter['amount'];
		$_SESSION['userid'] = $filter['userid'];
		$_SESSION['notify'] = $filter['notify'];
		$_SESSION['tool'] = $filter['tool'];

		$msg['invoiceid'] = $filter['invoiceid'];
		$msg['amount'] = $filter['amount'];
		$msg['qrdir'] = $qrcode['dir'];
		$msg['notify'] = "";
		$msg['tool'] = $filter['tool'];
		return $msg;
	}

	function Post($filter,$postdata) {
		$url = $filter['callback_url'];
	　　$post_data = $postdata;
	　　$ch = curl_init();
	　　curl_setopt($ch, CURLOPT_URL, $url);
	　　curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	　　// post数据
	　　curl_setopt($ch, CURLOPT_POST, 1);
	　　// post的变量
	　　curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	　　$output = curl_exec($ch);
	　　curl_close($ch);
	　　//打印获得的数据
		$output_array = json_decode($output,true);
	　　return $output_array;
	}

	function Done($qrid,$filter,$pdo) {
		$sql = 'UPDATE qrcode SET locked = 0,status = 0 WHERE id = :id';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':id',$qrid);
		$statement->execute();

		$sql = 'UPDATE transaction SET status = 1 WHERE invoiceid = :id AND status = 0';
		$statement = $pdo->prepare($sql);
		$statement->bindValue(':id',$filter['invoiceid']);
		$statement->execute();

		$postdata["x_status"] = 1;
		$postdata["x_invoice_id"] = $filter['invoiceid'];
		$postdata["x_amount"] = $filter['amount'];
		$postdata["x_hash"] = md5($invoiceId . $paymentAmount . $secretKey);

		$this->Post($filter,$postdata);

		$url = $filter['return_url'];
		Header("Location: $url"); 
	}

	function Control($config) {
		$filter = $this->Filter();
		$pdo = $this->NewPdo($config);
		$qrid = $this->CheckTransaction($filter['invoiceid'],$pdo);
		if ($qrid) {
			$qr = $this->GetQR($qrid,$pdo);
			if ($qr['status']== 1) {
				$this->Done($qrid,$filter,$pdo);
			}
			else {
				$this->UpdateExpir($qrid,$filter['invoiceid'],$pdo);

				$msg['invoiceid'] = $filter['invoiceid'];
				$msg['amount'] = $filter['amount'];
				$msg['qrdir'] = $qr['dir'];
				$msg['notify'] = "未检测到付款";
				$msg['tool'] = $filter['tool'];
				return $msg;
			}
		}
		else {
			$msg = $this->NewTransaction($pdo,$filter);
			return $msg;
		}
	}

}