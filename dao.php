<?php
//setlocale(LC_ALL, "ja_JP.UTF-8");

class dao_base{
	var $_conn = null;

	public function __construct( $conn ){
		$this->_conn = $conn;
	}

	public function qs( $str ){
		return $this->_conn->qstr( $str );
	}

	protected function onDebug(){ $this->_conn->debug = 1; }
	protected function offDebug(){ $this->_conn->debug = 0; }

}

class dao_nick extends dao_base{

	function getID( $name ){
		$sql = "SELECT id FROM nick WHERE name = ?";
		$id = $this->_conn->GetOne(
			$this->_conn->Prepare($sql),
			array($name)
		);
		return ($id !== false) ? $id : -1;
	}

	function getName( $id ){
		$sql = "SELECT name FROM nick WHERE id = ?";

		$name = $this->_conn->GetOne(
			$this->_conn->Prepare($sql),
			array($id)
		);
		
		return ($name !== false) ? $name : null;
	}

	function searchNick( $nick_str ){
	}
}

class dao_channel extends dao_base{
	function getID( $name ){
		$sql = "SELECT id FROM channel WHERE name = ?";

		$id = $this->_conn->GetOne(
			$this->_conn->Prepare($sql),
			array($name)
		);
		
		return ($id !== false) ? $id : -1;
	}

	function getName( $id ){
		$sql = "SELECT name FROM channel WHERE id = ? ";

		$name = $this->_conn->GetOne(
			$this->_conn->Prepare($sql),
			array($id)
		);

		return ($name !== false) ? $name : null;
	}

	function getList( $server = "" ){
		
		$sql = "SELECT * FROM channel";
		$values = array();

		if( !empty($server) ){
			$sql .= " WHERE name like ?";
			$values[] = "%@".$server;
		}

		return $this->_conn->getArray($this->_conn->Prepare($sql), $values);
	}

	function getUnreadList( $server = "" ){
		$sql = "SELECT 
					channel.id, 
					channel.name, 
					COALESCE(log_count.cnt,0) as cnt 
				FROM channel 
					LEFT JOIN (
						SELECT 
							channel.id, 
							count(*) as cnt 
						FROM channel 
							LEFT JOIN log ON channel.id = log.channel_id 
						WHERE channel.readed_on < log.created_on 
						GROUP BY channel.id
					) as log_count ON channel.id = log_count.id 
				WHERE view = ?";
		$values = array(1);

		if( !empty($server) ){
			$sql .= " WHERE name like ? ";
			$values[] = "%@".$server;
		}

		//$sql .= " ORDER BY cnt DESC";

		return $this->_conn->getArray($this->_conn->Prepare($sql), $values);
	}
	
	function updateReaded( $id = null ){
		$sql = "UPDATE channel SET readed_on = NOW()";
		$values = array();

		if( !is_null( $id ) ){
			$sql .=  " WHERE id = ?";
			$values[] = $id;
		}

		return $this->_conn->Execute($this->_conn->Prepare($sql), $values);
	}
}

class dao_log extends dao_base{
	function getLog( $channel_id, $log_id = null,  $num = 30, $type = "new"  ){
		$sql = "SELECT 
					log.id as id, 
					nick.name as nick, 
					log.log as log, 
					log.created_on as time, 
					log.is_notice as is_notice 
				FROM log 
					JOIN nick ON log.nick_id = nick.id 
				WHERE channel_id = ? ";
		$values = array($channel_id);

		if( !is_null( $log_id ) ){
			$sql .= " AND log.id ". ( $type!="old" ? '>' : '<' ). " ?";
			$values[] = $log_id;
		}
		
		$sql .= " ORDER BY log.created_on DESC LIMIT 0, ?";
		$values[] = $num;
		
		return $this->_conn->getArray($this->_conn->Prepare($sql), $values);
	}

	function getLogAll( $max_id ){
		if( !strlen($max_id) ){
			return null;
		}
		$sql = "SELECT 
					log.channel_id as channel_id, 
					log.id as id , 
					nick.name as nick, 
					log.log as log, 
					log.created_on as time,
					log.is_notice as is_notice 
				FROM log 
					JOIN nick ON log.nick_id = nick.id 
					JOIN channel ON log.channel_id = channel.id 
				WHERE channel.view = ? 
					AND log.id > ? 
				ORDER BY log.created_on DESC
				";

		$values = array(1, $max_id);

		return $this->_conn->getArray($this->_conn->Prepare($sql), $values);
	}

	function searchLog( $word, $channel_id = null ){
		$sql = "SELECT 
					log.id, 
					nick.name as nick, 
					channel.name as channel_name, 
					log.log as log, 
					log.created_on as time,
					log.is_notice as is_notice 
				FROM log 
					JOIN nick ON log.nick_id = nick.id 
					JOIN channel ON log.channel_id = channel.id  
				WHERE log.log like ? ";

		$values = array("%$word%");

		if( !is_null( $channel_id ) ){
			$sql .= " AND log.channel_id = ? ";
			$values[] = $channel_id;
		}
		$sql .= " ORDER BY log.created_on DESC LIMIT 0,30 ";

		return $this->_conn->getArray($this->_conn->Prepare($sql), $values);
	}

	function postLog( $message, $channel_id, $nick_id ){
		$sql = "INSERT INTO `log` 
					(`channel_id`, `nick_id`, `log`, `is_notice`, `created_on`, `updated_on`) 
				VALUES 
					(?, ?, ?, ?, NOW(), NOW() )
				";
		$values = array($channel_id, $nick_id, $message, 1);
		
		return $this->_conn->Execute($this->_conn->Prepare($sql), $values);
	}

	function getMaxID( ){
		$sql = "SELECT max(id) AS max_id FROM log";
		return $this->_conn->GetOne($this->_conn->Prepare($sql));
	}

}
