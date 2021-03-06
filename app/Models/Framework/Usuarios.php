<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Systemsystemusers as SysUsr;
use App\Models\Sistemas;
use App\Models\Roles;
use Helpme;
use DB;

class Usuarios extends Model
{
  protected $table = 'fw_usuarios';
  protected $primaryKey = 'id_usuario';
  public $timestamps = false;


  /********************************************************************************************************/
  /********************************************************************************************************/

  static public function startCurl($metodo, $headers_ext, $post_send, $id_sistema){

    $keys = Sistemas::systemKey($id_sistema);

    foreach ($keys as $key)
    {
        $app_secret =  $key->system_key;
        $app_name =  $key->nombre;
        $app_url =  $key->url;
    }

    $sign = hash_hmac('sha256', $post_send, $app_secret, false);

    $headers = array(
       'systemverify-Signature:'.$sign,
       'system:'.$app_name,
       'system-id:'.$id_sistema,
       'ip:'.$_SERVER['REMOTE_ADDR']
    );

    $headers = array_merge($headers, $headers_ext);

    $curl = null;
    $curl = curl_init($app_url.'webhook/' . $metodo);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_send);
    return curl_exec($curl);
  }

  static public function setRemoteRol($id_rol, $id_sistema){

    $rol_data = json_encode(Roles::getDataRol($id_rol));
    $metodo = 'syncrol';
    $post_send = json_encode(array('proceso' => $metodo, 'roldata' => $rol_data));
    $headers = array('roldata:'.$rol_data);
    $data = self::startCurl( $metodo, $headers, $post_send, $id_sistema);
    $valid = ($data >= 1)?true:false;
    return $valid;
  }

  static public function setRemoteMetodo($datametodo){
    $id_sistema = $datametodo->id_sistema;
    $metodo = 'syncmetodo';
    $post_send = json_encode(array('proceso' => $metodo, 'metododata' => $datametodo));
    $headers = array('metododata:'.$datametodo);
    $data = self::startCurl( $metodo, $headers, $post_send, $id_sistema);
    $valid = ($data >= 1)?true:false;
    return $valid;
  }

  static public function updateRemoteRole($id_rol, $id_sistema){
    $metodo = 'updateroldata';
    $rol_data = json_encode(Roles::getDataRol($id_rol));
    $post_send = json_encode(array('proceso' => 'updateroldata', 'roldata' => $rol_data));
    $headers = array('roldata:'.$rol_data);
    return self::startCurl( $metodo, $headers, $post_send, $id_sistema);
  }

  static public function populateRemote($id_sistema, $ids_inserts){
    $metodo = 'populate';
    $post_send = json_encode(array('ids_inserts' => $ids_inserts));
    $headers = array();
    return self::startCurl( $metodo, $headers, $post_send, $id_sistema);
  }

  static public function getModelosRemotos($id_sistema){
    $metodo = 'backup';
    $post_send = json_encode(array('proceso' => $metodo));
    $headers = array();
    return self::startCurl( $metodo, $headers, $post_send, $id_sistema);
  }

  static public function updateRemoteUser($id_usuario, $id_sistema){

    $user_data = json_encode(Usuarios::datos_usuario($id_usuario));
    $id_rol = SysUsr::getRolOfUserSys($id_usuario, $id_sistema);
    $cat_status = SysUsr::getCatStatusOfUserSys($id_usuario, $id_sistema);
    $metodo = 'updateuser';
    $post_send = json_encode(array('proceso' => $metodo, 'userdata' => $user_data));
    $headers = array(
       'userdata:'.$user_data,
       'idrol:'.$id_rol,
       'catstatus:'.$cat_status
    );
    $data = self::startCurl( $metodo, $headers, $post_send, $id_sistema);
    $valid = ($data >= 1)?true:false;
    return $valid;
  }

  static public function setRemoteUser($id_usuario, $id_sistema){

    $user_data = json_encode(Usuarios::datos_usuario($id_usuario));
    $id_rol = SysUsr::getRolOfUserSys($id_usuario, $id_sistema);
    $metodo = 'syncuser';
    $post_send = json_encode(array('proceso' => $metodo, 'userdata' => $user_data));
    $headers = array(
       'userdata:'.$user_data,
       'idrol:'.$id_rol
    );
    $data = self::startCurl( $metodo, $headers, $post_send, $id_sistema);
    $valid = ($data >= 1)?true:false;
    return $valid;
  }

  static public function updateRemoteUserFor($id_usuario){
    $sistemas = SysUsr::getSysOfUser($id_usuario);
    foreach ($sistemas as $sistema) {
      self::updateRemoteUser($id_usuario, $sistema->id_sistema);
    }
  }

/********************************************************************************************************/
/********************************************************************************************************/

    static function getSysOfUser($id_usuario){
        $sistemas = SysUsr::getSysOfUser($id_usuario);
        foreach($sistemas as $sistema){
          SysUsr::updateRelationStatus($id_usuario, $id_sistema, 17);

          if(self::updateRemoteUser($id_usuario, $sistema->id_sistema))
            SysUsr::updateRelationStatus($id_usuario, $id_sistema, 3);
        }
    }

  static function usuarios_bloqueados(){
    return DB::table('fw_usuarios')->where('cat_status','=',9)->count();
  }

  static function obtener_usuarios(){
    return DB::table('fw_usuarios')->get();
  }

  static function updateToken($id_usuario, $id_sistema){
    $query_resp = DB::table('fw_usuarios')
            ->where('id_usuario', $id_usuario)
            ->update([
                'token'=> Helpme::token(32),
                'user_mod'=> $_SESSION['id_usuario']
            ]);

    if($query_resp){
      self::updateRemoteUser($id_usuario, $id_sistema);
      $respuesta = array('resp' => true, 'udtlc' => $query_resp);
    }else{
      $respuesta = array('resp' => false, 'udtlc' => 'error al actualizar el token');
    }
    return json_encode($respuesta);
  }


  static function baja_usuario($id_usuario){
    $query_resp = DB::table('fw_usuarios')
            ->where('id_usuario', $id_usuario)
            ->update([
                'cat_status'=> 5,
                'token'=> Helpme::token(32),
                'user_mod'=> $_SESSION['id_usuario']
            ]);
    if($query_resp){
      self::updateRemoteUserFor($id_usuario);
      $respuesta = array('resp' => true , 'mensaje' => 'La baja del usuario se realizó de manera correcta.');
    }else{
      $respuesta = array('resp' => false , 'mensaje' => 'Error en el sistema.' , 'error' => 'Error al dar de baja al usuario.' );
    }
    return $respuesta;
  }

  static function set_avatar($avatar){
    $perfil = self::perfil_usuario($_SESSION['id_usuario']);
    $avatar_actual = $perfil['avatar'];

      if($avatar_actual){
        unlink('../storage/perfiles/'.$avatar_actual);
      }

      DB::table('fw_usuarios_config')
      ->where('id_usuario', $_SESSION['id_usuario'])
      ->update([
          'avatar'=> $avatar,
          'user_mod'=> $_SESSION['id_usuario']
      ]);
      return array('resp' => true);
  }

  static function acceptTyc($stat){
    $result = DB::table('fw_usuarios_config')
            ->where('id_usuario', $_SESSION['id_usuario'])
            ->update([
                'aceptar_tyc'=> $stat,
                'user_mod'=> $_SESSION['id_usuario']
            ]);

    if($result){
      $_SESSION['tyc'] = 'SI';
      $respuesta = array('resp' => true , 'dispositivo' => $_SESSION['dispositivo'] );
    }else{
      $_SESSION['tyc'] = 'NO';
      $respuesta = array('resp' => false , 'dispositivo' => $_SESSION['dispositivo'] );
    }
    return $respuesta;
  }

  static function desbloquea_usuario($id_usuario){

    $query_resp = Usuarios::find($id_usuario);
    $query_resp->cat_status = 3;
    $query_resp->user_mod = $_SESSION['id_usuario'];
    $query_resp->save();

    if($query_resp){
      // intentos en cero
      $update_intentos = DB::table('fw_login_log')
              ->where('id_usuario', $id_usuario)
              ->orderBy('id_login_log', 'desc')
              ->limit(1)
              ->update([
                  'intentos'=> 0
              ]);

      if($update_intentos>=0){
           $respuesta = array('resp' => true , 'mensaje' => 'El usuario se desbloqueo correctamente.' );
      }else{
        $respuesta = array('resp' => false , 'mensaje' => 'Error en el sistema.' , 'error' => 'Error al desbloquear usuario.' );
      }
    }else{
      $respuesta = array('resp' => false , 'mensaje' => 'Error en el sistema.' , 'error' => 'Error al desbloquear usuario.' );
    }
    return $respuesta;
  }

  static function desbloquear_usuarios(){

    $query_resp = DB::table('fw_usuarios')
                ->select('id_usuario')
                ->where('cat_status','=',9)->get();

    foreach ($query_resp as $usuarios)
    {
        // cambia estatus
        $cambia_estatus = Usuarios::find($usuarios->id_usuario);
        $cambia_estatus->cat_status = 3;
        $cambia_estatus->user_mod = $_SESSION['id_usuario'];
        $cambia_estatus->save();

        // intentos en cero
        $update_intentos = DB::table('fw_login_log')
                ->where('id_usuario', $usuarios->id_usuario)
                ->orderBy('id_login_log', 'desc')
                ->limit(1)
                ->update([
                    'intentos'=> 0
                ]);
    }

    if(count($query_resp)>=0){
      // intentos en cero
        $respuesta = array('resp' => true , 'mensaje' => 'Los usuarios se desbloquearon correctamente.' );
    }else{
      $respuesta = array('resp' => false , 'mensaje' => 'Error en el sistema.' , 'error' => 'Error al desbloquear usuario.' );
    }
    return $respuesta;
  }

  static function pass_chge_stat($stat,$id_usuario){
    $result = DB::table('fw_usuarios')
            ->where('id_usuario', $id_usuario)
            ->update([
                'cat_pass_chge'=> $stat,
                'token'=> Helpme::token(32),
                'user_mod'=> $_SESSION['id_usuario']
            ]);
    self::updateRemoteUserFor($id_usuario);
    return $result;
  }

  static function updateIngreso($fecha_ingreso,$id_usuario){
    DB::table('fw_usuarios_config')
            ->where('id_usuario', $id_usuario)
            ->update([
                'fecha_ingreso'=> $fecha_ingreso,
                'user_mod'=> $_SESSION['id_usuario']
            ]);
  }

  static function editar_perfil($request){
    if(($request->input('password') != '') && ($request->input('password2') != '')){
      if(($request->input('password') == $request->input('password2'))&&($request->input('password'))){
        DB::table('fw_usuarios')
                ->where('id_usuario', $_SESSION['id_usuario'])
                ->update(['password'=> md5($request->input('password'))]);
      }
    }

        DB::table('fw_usuarios')
                ->where('id_usuario', $_SESSION['id_usuario'])
                ->update([
                    'correo'=> $request->input('correo'),
                    'nombres'=> $request->input('nombres'),
                    'apellido_paterno'=> $request->input('apellido_paterno'),
                    'apellido_materno'=> $request->input('apellido_materno'),
                    'user_mod'=> $_SESSION['id_usuario']
                ]);

        if(self::crear_perfil($_SESSION['id_usuario'])){

          $activar_paginado = (!empty ($request->input('activar_paginado'))) ? 1 : 0;
          $paginacion = $request->input('paginacion') ? $request->input('paginacion') : 0;

          DB::table('fw_usuarios_config')
                ->where('id_usuario', $_SESSION['id_usuario'])
                ->update([
                    'paginacion'=> $paginacion,
                    'activar_paginado'=> $activar_paginado,
                    'user_mod'=> $_SESSION['id_usuario']
                ]);
        }

        $respuesta = array('resp' => true , 'mensaje' => 'El perfil guardado correctamente.', 'chackbox' => $activar_paginado, 'new_name' => $request->input('nombres') );

    return $respuesta;
  }

  static function editar_usuario($request){
    if(($request->input('password') != '') && ($request->input('password2') != '')){
      if(($request->input('password') == $request->input('password2'))&&($request->input('password'))){
        DB::table('fw_usuarios')
                ->where('id_usuario', $request->input('id_usuario'))
                ->update(['password'=> md5($request->input('password'))]);
      }
    }
        $query_resp = DB::table('fw_usuarios')
                          ->where('id_usuario', $request->input('id_usuario'))
                          ->update([
                              'id_ubicacion' => $request->input('id_ubicacion'),
                              'cat_status'=> $request->input('cat_status'),
                              'cat_pass_chge'=> $request->input('change_pass'),
                              'correo'=> $request->input('correo'),
                              'id_rol'=> $request->input('id_rol'),
                              'nombres'=> $request->input('nombres'),
                              'apellido_paterno'=> $request->input('apellido_paterno'),
                              'apellido_materno'=> $request->input('apellido_materno'),
                              'token'=> Helpme::token(32),
                              'user_mod'=> $_SESSION['id_usuario']
                          ]);

    if($query_resp){
      self::updateRemoteUserFor($request->input('id_usuario'));
      self::updateIngreso($request->input('fecha_ingreso'),$request->input('id_usuario'));
      $respuesta = array('resp' => true , 'mensaje' => 'Registro guardado correctamente.' );
    }else{
      $respuesta = array('resp' => false , 'mensaje' => 'Error en el sistema.' , 'error' => 'Error al insertar registro.' );
    }
    return $respuesta;
  }

  static function eliminar_token($token){
    $query_resp = DB::table('fw_lost_password')
                          ->where('token', $token)
                          ->delete();
    return $query_resp;
  }

  static function cambiar_password($pass,$id_usuario){

    if(isset($_SESSION['id_usuario'])){$mod_user = $_SESSION['id_usuario'];}else{$mod_user = $id_usuario;}

    $query_resp = DB::table('fw_usuarios')
                          ->where('id_usuario', $id_usuario)
                          ->update([
                              'password' => md5($pass),
                              'token'=> Helpme::token(32),
                              'user_mod'=>$mod_user
                          ]);
    self::updateRemoteUserFor($id_usuario);
    return $query_resp;
  }

  static function verifica_token($token){
    $lost_pass = DB::table('fw_lost_password')->where('token','=',$token)->get();

    $array = array();

    if(count($lost_pass)>=1){
      foreach ($lost_pass as $row) {
        $array['token'] 		= $row->token;
        $array['id_usuario'] 	= $row->id_usuario;
        $array['correo'] 		= $row->correo;
        $array['valid'] 		= true;
      }
    }else{
      $array['valid'] 		= false;
    }
    return $array;
  }

  static function consulta_correo($correo){

    $query_resp = DB::table('fw_usuarios')->where('correo','=',$correo)->count();

    if($query_resp > 0){
      $respuesta = array('resp' => true, 'datos' => $query_resp );
    }else{
      $respuesta = array('resp' => false, 'mensaje' => 'Sin resultados en busqueda.'  );
    }
    return $respuesta;
  }

  static function consulta_login($usuario){

    $query_resp = DB::table('fw_usuarios')->where('usuario','=',$usuario)->count();

    if($query_resp > 0){
      $respuesta = array('resp' => true, 'datos' => $query_resp );
    }else{
      $respuesta = array('resp' => false, 'mensaje' => 'Sin resultados.'  );
    }
    return $respuesta;
  }

  static function valida_login_correo($usuario,$correo){
    $resp = true;
    $error = "";
    $mensaje = "";

    $resp_login = self::consulta_login($usuario);
    $resp_correo = self::consulta_correo($correo);
    if($resp_login['resp'] == true ){
      $resp=false;
      $mensaje = 'Error por duplicidad de datos.';
      $error.= 'Nombre de usuario no disponible.<br />';
    }
    if($resp_correo['resp'] == true ){
      $resp=false;
      $mensaje = 'Error por duplicidad de datos.';
      $error.= 'Cuenta de correo electrónico no disponible.';
    }
    return array('resp' => $resp, 'mensaje' => $mensaje, 'error' => $error );
  }

  static function guardar_usuario($request){

    $respuesta = self::valida_login_correo($request->input('usuario'),$request->input('correo'));

    if($respuesta['resp'] == true ){

      $id_usuario = DB::table('fw_usuarios')->insertGetId(
          [
              'id_ubicacion' => $request->input('id_ubicacion'),
              'password' => md5($request->input('password')),
              'cat_pass_chge' => $request->input('change_pass'),
              'cat_status' => $request->input('cat_status'),
              'usuario' => trim($request->input('usuario')),
              'correo' => $request->input('correo'),
              'id_rol' => $request->input('id_rol'),
              'nombres' => $request->input('nombres'),
              'apellido_paterno' => $request->input('apellido_paterno'),
              'apellido_materno' => $request->input('apellido_materno'),
              'token'=> Helpme::token(32),
              'user_alta' => $_SESSION['id_usuario'],
              'fecha_alta' => date("Y-m-d H:i:s")
          ]
      );

      self::crear_perfil($id_usuario);
      self::updateIngreso($request->input('fecha_ingreso'),$id_usuario);

      if($id_usuario){
        self::updateRemoteUserFor($id_usuario);
        $respuesta = array('resp' => true , 'mensaje' => 'Registro guardado correctamente.', 'id_rol' =>  $request->input('id_rol'), 'id_usuario' => $id_usuario);
      }else{
        $respuesta = array('resp' => false , 'mensaje' => 'Error en el sistema.' , 'error' => 'Error al insertar registro.' );
      }

    }
    return $respuesta;
  }

  static function agregar_usuario($request){

    if( $request->input('password') == $request->input('password2') ){
      $respuesta = self::guardar_usuario($request);
    }else{
      $respuesta = array('resp' => false , 'mensaje' => 'Error en captura de datos.' , 'error' => 'Las contraseñas ingresadas no son iguales.' );
    }

    return $respuesta;
  }

  static function perfil_usuario($user_id){
    $perfil = DB::table('fw_usuarios_config')->where('id_usuario', $user_id)->get();

    if($perfil[0]->id_usuario){
      foreach ($perfil as $row) {
          $array['avatar'] 			= $row->avatar;
          $array['paginacion'] 		= $row->paginacion;
          $array['activar_paginado'] 	= $row->activar_paginado;
      }
    }else{
      self::crear_perfil($user_id);
      self::perfil_usuario($user_id);
    }
    return $array;
  }

  static function crear_perfil($id_usuario){
    $count = DB::table('fw_usuarios_config')->where('id_usuario', '=', $id_usuario)->count();
    if($count == 1){
      return true;
    }else{
      DB::table('fw_usuarios_config')->insert(
          [
              'id_usuario' => $id_usuario,
              'user_alta' => $_SESSION['id_usuario'],
              'fecha_alta' => date("Y-m-d H:i:s")
          ]
      );
      return true;
    }
  }

  static function datos_usuario($user_id){
    return Usuarios::find($user_id);
  }
}
