<?php

use Illuminate\Database\Seeder;

class populateRoles extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      DB::table('fw_roles')->insert(
      array(
      'id_rol'=>1,
      'cat_tiporol'=>6,
      'id_sistema'=>1,
      'descripcion'=>'Desarrollador',
      'user_alta'=>0,
      'user_mod'=>0,
      'fecha_alta'=>'2016-11-16 14:41:31',
      'fecha_mod'=>'2016-11-16 14:41:31'
      ) );
    }
}