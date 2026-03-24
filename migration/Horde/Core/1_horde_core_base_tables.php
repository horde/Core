<?php

class HordeCoreBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_signups', $this->tables())) {
            $t = $this->createTable('horde_signups', ['autoincrementKey' => ['user_name']]);
            $t->column('user_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('signup_date', 'integer', ['null' => false]);
            $t->column('signup_host', 'string', ['limit' => 255, 'null' => false]);
            $t->column('signup_data', 'text', ['null' => false]);
            $t->end();
        }
    }

    public function down()
    {
        $this->dropTable('horde_signups');
    }
}
