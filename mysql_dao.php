<?php
include("jsonwrapper/jsonwrapper_helper.php");
include("phpmailer/class.phpmailer.php");
include("forceUTF8/Encoding.php");
include("firephp/fb.php");

class DAO {
    protected $stmt;
    public $retValue,$query;
    public $conexao = null;
    public $firebug = false;	
    private $db = "your_db";
    private $user = "your_user";
    private $pass = "your_password";

    public function __construct($connection_init=true) {
        if($connection_init == true) {
            $this->inicializar_conexao();
        }
    }	

    public function inicializar_conexao() {
        try {
            $this->conexao = new PDO($db, $user, $pass);
        } catch(PDOException $e) {
            echo $e->getMessage();
        }
        $this->conexao->beginTransaction();
    }

    public function executar($query,$commit=false) {
        $this->query = $query;
        return($this->executar_query($query,$commit));
    }

    public function inserir($tabela,$campos,$commit=false,$returning=null) {
        $this->query = "INSERT INTO ".$tabela."(";
        $bindings = array();
        for($i = 0; $i < count($campos); $i++) {
            $bindings[] = ":FIELD_".$i;
        }
        $this->query .= join(",",array_keys($campos)).")VALUES(".join(",",$bindings).")";
        return $this->executar_query_com_bindings($this->query,array_values($campos),$commit,$returning);
    }

    public function atualizar($tabela,$campos,$condicoes=null,$commit=false) {
        $this->query = "UPDATE ".$tabela." SET ";
        $bindings = array();
        $valores = array_values($campos);
        $num_campos = count($campos);
        $i = 0;
        foreach($campos as $k=>$v) {
            $bindings[] = strtoupper($k)." = ".":FIELD_".$i;
            $i++;
        }
        $this->query .= join(",",$bindings);
        if($condicoes != null) {
            $this->query .= " WHERE ";
            $num_campos--;
            foreach($condicoes as $k=>$v) {
                $num_campos++;
                $this->query .= strtoupper($k)." = :FIELD_".$num_campos." AND ";
                $valores[] = $v;
            }
            $this->query = substr($this->query,0,-4);
        }
        return $this->executar_query_com_bindings($this->query,$valores,$commit);
    }

    public function excluir($tabela,$campos,$commit=false) {
        $this->query = "DELETE FROM ".$tabela." WHERE ";
        $bindings = array();
        $i = 0;
        foreach($campos as $k=>$v) {
            $bindings[] = $k." = ".":FIELD_".$i;
            $i++;
        }
        $this->query .= join(" AND ",$bindings);
        return $this->executar_query_com_bindings($this->query,array_values($campos),$commit);
    }

    public function buscar($query,$bindings = null) {
        $this->query = $query;
        if($bindings == null) {
            $this->executar_query($this->query);
        } else {
            $this->executar_query_com_bindings($this->query,$bindings);
        }
        return($this->stmt->fetch());
    }

    public function buscar_varios($query,$bindings = null) {
        $this->query = $query;
        if($bindings == null) {
            $this->executar_query($this->query);
        } else {
            $this->executar_query_com_bindings($this->query,$bindings);
        }
        return($this->stmt->fetchAll());
    }

    public function converter_para_utf8($data) {
        return($this->iterar_dados_para_conversao($data,'UTF-8',true));
    }

    public function converter_para_iso8859($data) {
        return($this->iterar_dados_para_conversao($data,'ISO-8859-1',true));
    }

    public function sort_array_by_key(array $array, $key, $asc = true) {
        $result = array();	
        $values = array();
        foreach ($array as $id => $value) {
            $values[$id] = isset($value[$key]) ? $value[$key] : '';
        }	
        if ($asc) {
            asort($values);
        }
        else {
            arsort($values);
        }	
        foreach ($values as $key => $value) {
            $result[$key] = $array[$key];
        }			
        return $result;
    }

    public function htmlize($data) {
        if(!function_exists('internal_htmlize')) {
            function internal_htmlize(&$item, $key) {
                if(is_string($item)) {
                    $item = htmlentities($item);
                }
            }
        }
        array_walk_recursive($data, "internal_htmlize");
        return $data;
    }

    public function renderJSON($data,$htmlize_param=false) {
        header("Content-Type: application/json");
        flush();
        if($data == false) {
            $data = array();
        } else {
            if($htmlize_param == true) {
                $data = $this->htmlize($data);
            }
        }
        echo($this->json_encode($data));
    }

    public function renderCSV($data,$filename="file") {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=".$filename.".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        flush();
        $outstream = fopen("php://output", "w");
        if($data == false) {
            $data = array();
        }
        function __outputCSV(&$vals, $key, $filehandler) {
            fputcsv($filehandler, $vals,chr(9),chr(0)); // add parameters if you want
        }
        array_walk($data, "__outputCSV", $outstream);
        fclose($outstream);
    }

    public function renderTXT($data,$filename="file") {
        header("Content-type: application/txt");
        header("Content-Disposition: attachment; filename=".$filename.".txt");
        flush();
        echo($data);
    }

    public function json_encode($string) {
        return(json_encode($string));
    }

    public function datetimedb($data=null) {
        if($data != null) {
            return date('d/m/Y H:i:s',$this->datetotime($data));
        } else {
            return date('d/m/Y H:i:s');
        }
    }

    public function datetotime($data=null,$time_mod=null) {
        if($data == null) {
            $data = date('d/m/Y');
        }
        $data = split("/",$data);
        $data = $data[1]."/".$data[0]."/".$data[2];
        if($time_mod != null) {
            return strtotime($time_mod,strtotime($data));
        } else {
            return strtotime($data);
        }
    }

    public function horario_em_intervalo($h,$m,$h1,$m1,$h2,$m2){
        $h = intval($h);
        $m = intval($m);
        $h1 = intval($h1);
        $m1 = intval($m1);
        $h2 = intval($h2);
        $m2 = intval($m2);
        //casos como 00:00 - 14:00
        if ($h1 < $h2){
            if (($h < $h1) || ($h > $h2)){
                return false;
            } else if (($h == $h1) || ($h == $h2)) {
                if ($h == $h1){
                    if ($m < $m1){
                        return false;
                    }
                } else {
                    if ($m > $m2){
                        return false;
                    }
                }
            }
        }
        //casos como 12:00 - 12:30 e 12:30-12:00
        if ($h1 == $h2){
            if ($m1 > $m2){
                //divida em 2 intervalos
                //12:30-12:00 => 12:30-23:59 e 00:00-12:00
                $tmp1 = timeInInterval($h,$m,$h1,$m1,23,59);
                $tmp2 = timeInInterval($h,$m,0,0,$h2,$m2);
                if (($tmp1 == 0) && ($tmp2 == 0)){
                    return false;
                }
            } else {
                if ($h != $h2){
                    return false;
                } else if (($m > $m2) || ($m < $m1)) {
                    return false;
                }
            }
        }
        //casos como 08:00 - 01:00
        if ($h1 > $h2){
            //divida em dois intervalos : 08:00 - 23:59 e 00:00-01:00
            $tmp1 = timeInInterval($h,$m,$h1,$m1,23,59);
            $tmp2 = timeInInterval($h,$m,0,0,$h2,$m2);
            if (($tmp1 == 0) && ($tmp2 == 0)){
                return false;
            }
        }
        return true;
    }

    public function enviar_email($from,$fromName,$addresses,$subject,$body) {
        $mail = new PHPMailer();
        $mail->SMTPAuth = false;
        $mail->IsSMTP();
        $mail->Host = "foo.bar.br";
        $mail->Port = 25;
        $mail->From = $from;
        $mail->FromName = $this->converter_dado($fromName,"ISO-8859-1");
        $mail->IsHTML(false);
        $mail->CharSet = 'ISO-8859-1';
        $mail->Body = $this->converter_dado($body,"ISO-8859-1");
        if(is_array($addresses)) {
            foreach($addresses as $address) {
                $mail->AddAddress($address);
            }
        } else {
            $mail->AddAddress($addresses);
        }
        $mail->Subject = $this->converter_dado($subject,"ISO-8859-1");;
        return $mail->Send();
    }

    public function ultimo_dia_do_mes($month=null,$year=null,$format="d/m/Y") {
        if($month == null) {
            $month = date("m");
        } else {
            $month = sprintf("%1$02d",$month);
        }
        if($year == null) {
            $year = date("Y");
        }
        return date($format,strtotime('-1 second',strtotime('+1 month',strtotime($month.'/01/'.$year.' 00:00:00'))));
    }	

    public function escape($str,$db='oracle') {
        if(is_array($str))
            return array_map(__METHOD__, $str);
        if(!empty($str) && is_string($str)) {
            $str = str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
                array('\\\\', '\\0', '\\n', '\\r', ($db == 'oracle' ? "''" : "\\'"), '\\"', '\\Z'), stripslashes($str));
        }
        return $str;
    }

    public function __destruct() {
        if($this->conexao != null) {
            $this->conexao->commit();
            $this->conexao = null;
        }
    }

    public function executar_query($query) {
        $this->stmt = $this->conexao->prepare($query);
        return $this->verifica_transacao($this->stmt->execute());
    }

    private function check_param_type($v) {
        if(is_int($v))
            $param = PDO::PARAM_INT;
        elseif(is_bool($v))
            $param = PDO::PARAM_BOOL;
        elseif(is_null($v))
            $param = PDO::PARAM_NULL;
        elseif(is_string($v))
            $param = PDO::PARAM_STR;
        else
            $param = FALSE;
        return $param;
    }

    public function executar_query_com_bindings($query,$valores,$returning=null) {
        $this->stmt = $this->conexao->prepare($query);
        if($this->is_assoc($valores)) {
            foreach($valores as $k => $v) {
                $param = $this->check_param_type($v);
                $this->stmt->bindValue($k,$v,$param);
            }
        } else {
            for($i = 0; $i < count($valores); $i++) {
                $param = $this->check_param_type($valores[$i]);
                $this->stmt->bindValue(":FIELD_".$i,$valores[$i],$param);
            }
        }
        if($returning != null) {
            $this->retValue = $this->conexao->lastInsertId();
        }
        return $this->verifica_transacao($this->stmt->execute());
    }

    private function verifica_transacao($status) {
        if($status) {
            return($status);
        } else {
            if($this->firebug == false) {
                error_log($this->conexao->errorInfo());
            } else {
                FB::log("Erro na query: ".$this->query);
                FB::log($this->conexao->errorInfo());
            }	
            $this->conexao->rollback();
            return false;
        }
    }

    public function log($msg = "") {
        FB::log($this->converter_para_utf8($msg));
    }

    private function iterar_dados_para_conversao($data,$charset,$escape=true) {
        $ref = $this;
        if(!function_exists('internal_func')) {
            function internal_func(&$item, $key){
                $ref = new DAO(false);
                if($item != null) {
                    if($escape == true) {
                        $item = $ref->escape($ref->converter_dado($item,$charset));
                    } else {
                        $item = $ref->converter_dado($item,$charset);
                    }
                }
            }
        }
        if(is_string($data)) {
            if($escape == true) {
                $data = $this->escape($this->converter_dado($data,$charset));
            } else {
                $data = $this->converter_dado($data,$charset);
            }
        } else {
            array_walk_recursive($data, "internal_func");
        }
        return($data);
    }

    public function converter_dado($data,$charset) {
        if($charset == 'UTF-8') {
            return Encoding::toUTF8($data);
        } else {
            return Encoding::toLatin1($data);
        }
    }

    public function is_assoc($array) {
        foreach (array_keys($array) as $k => $v) {
            if ($k !== $v) {
                return true;
            }
        }
        return false;
    }
}
