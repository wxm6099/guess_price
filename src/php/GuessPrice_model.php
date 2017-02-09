<?php

$common_file = APPPATH.'controllers/common.php';
if(file_exists($common_file))
{
    require_once($common_file);
}
else
{
    print($common_file.' not exits');
}

class GuessPrice_model extends CI_Model {
    private static $config_array;

    public function __construct()
    {
        $this->load->database();

        self::$config_array = get_gp_config();
    }

    public function get_group_id()
    {
        $query = $this->db->get('group_id_t');
        return $query->result_array();
    }

    public function set_wx_user_info($access_token, $info_json)
    {
        $insert_sql = 'INSERT INTO user_t (openid, nickname, sex, province, city,
                        country, headimgurl, access_token, is_removed) VALUES ('
                        .$this->db->escape($info_json->{'openid'}).', '
                        .$this->db->escape($info_json->{'nickname'}).', '
                        .$info_json->{'sex'}.','
                        .$this->db->escape($info_json->{'province'}).', '
                        .$this->db->escape($info_json->{'city'}).', '
                        .$this->db->escape($info_json->{'country'}).', '
                        .$this->db->escape($info_json->{'headimgurl'}).', '
                        .$this->db->escape($access_token).', 0);';
        // echo $insert_sql.'<br>';
        $this->db->query($insert_sql);
        // echo $this->db->affected_rows();

        return $this->db->affected_rows();
    }

    /* return true or false. */
    public function set_express_info($openid, $name, $address, $phone)
    {
        $update_sql = 'UPDATE user_t SET 
                address = '.$this->db->escape($address).', 
                name = '.$this->db->escape($name).', 
                phone = '.$this->db->escape($phone).' 
                WHERE openid = '.$this->db->escape($openid).';';
        // echo $update_sql.'<br>';
        $this->db->trans_start();
        $this->db->query($update_sql);
        $this->db->trans_complete();

        // check if update successfully
        if (false == $this->db->trans_status())
        {
            return 0;
        }
        else
        {
            return 1;
        }
    }

    public function can_create_group($openid)
    {
        $query_sql = 'SELECT COUNT(*) AS c FROM group_t WHERE leaderid='
                .$this->db->escape($openid).';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();
        // echo $row['c'].'<br>';

        return (0 == $row['c']) ? 1 : 0;
    }

    public function get_groupid_created($openid)
    {
        $query_sql = 'SELECT id FROM group_t WHERE leaderid= '.$this->db->escape($openid).' LIMIT 1;';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();

        if (0 == count($row))
        {
            return 0;
        }
        else
        {
            return $row['id'];
        }
    }

    public function can_join_group($openid)
    {
        $query_sql = 'SELECT join_group_count FROM user_t WHERE openid='
                .$this->db->escape($openid).';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();
        // echo count($row).'<br>';
         
        if (0 == count($row))
        {
            # no user info record
            return 0; 
        }
        else if (self::$config_array['total_join_group_count'] <= $row['join_group_count'])
        {
            return 0;            
        } 
        else
        {
            return 1;
        }
    }

    /* 每个人只能领一次奖 */
    public function is_game_over()
    {
        # TODO: check sql performance
        $query_sql = 'SELECT COUNT(DISTINCT memberid) AS c FROM group_member_t WHERE groupid IN (SELECT id FROM group_t WHERE member_count = '.self::$config_array['group_member_max_count'].' AND is_price_right = 1);';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();

        /* 如果活动还没有结束，必须保证一个团的奖品数 */
        if ((self::$config_array['total_prize_count'] - self::$config_array['group_member_max_count']) < $row['c'])
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }

    public function make_group_id()
    {
        /* 使用事务*/
        $this->db->trans_start();
        $query_sql = 'REPLACE INTO group_id_t (extra) VALUES (\'a\');';
        // echo $query_sql.'<br>';
        $this->db->query($query_sql);
        $query_sql = 'SELECT LAST_INSERT_ID() AS id;';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();
        $this->db->trans_complete();

        if ($this->db->trans_status())
        {
            return $row['id'];
        }
        else
        {
            return 0;
        }
    }

    public function set_new_group($openid, $group_id, $is_price_right)
    {
       $insert_sql = 'INSERT INTO group_t (id, leaderid, member_count, is_price_right) VALUES ('.$group_id.', '.$this->db->escape($openid).', 1, '.$is_price_right.');'; 
       // echo $insert_sql.'<br>';
       $this->db->query($insert_sql);
       // echo $this->db->affected_rows().'<br>';

       return $this->db->affected_rows(); 
    }

    public function update_group_info($group_id, $is_price_right)
    {
        if (1 == $is_price_right)
        {
            $update_sql = 'UPDATE group_t SET member_count = member_count + 1, is_price_right = '.$is_price_right.' WHERE id = '.$group_id.';';
        }
        else
        {
            $update_sql = 'UPDATE group_t SET member_count = member_count + 1 WHERE id = '.$group_id.';';
        }

        // echo $update_sql;
        $this->db->query($update_sql);
        
        return $this->db->affected_rows();
    }

    /* return true or false. */
    public function set_group_member($openid, $group_id, $guess_price)
    {
        $insert_sql = 'INSERT INTO group_member_t (groupid, memberid, price) SELECT * FROM (SELECT '.$group_id.', '.$this->db->escape($openid).', '.$guess_price.') AS tmp WHERE NOT EXISTS (SELECT groupid FROM group_member_t WHERE groupid = '.$group_id.' AND memberid = '.$this->db->escape($openid).') LIMIT 1;';
        // echo $insert_sql.'<br>';
        $this->db->trans_start();
        $this->db->query($insert_sql);
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function get_group_memberid($group_id)
    {
        $query_sql = 'SELECT memberid, price FROM group_member_t WHERE groupid = '.$group_id.';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);

        return $query->result_array();
    }

    public function get_group_leaderid($group_id)
    {
        $query_sql = 'SELECT leaderid FROM group_t WHERE id = '.$group_id.';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();

        if (0 == count($row)) 
        {
            return 0;
        } 
        else
        {
            return $row['leaderid'];
        }
    }

    public function get_group_member_count($group_id)
    {
        $query_sql = 'SELECT member_count FROM group_t WHERE id = '.$group_id.';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();
        
        if (0 == count($row))
        {
            return -1;
        }
        else
        {
            return $row['member_count'];
        }
    }

    public function get_group_winning_state($group_id)
    {
        $query_sql = 'SELECT is_price_right FROM group_t WHERE id = '.$group_id.';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $row = $query->row_array();

        if (0 == count($row))
        {
            // TODO: group not exists.
            return -1;
        }
        else
        {
            return $row['is_price_right'];
        }
    }

    public function add_winner($group_id)
    {
        $query_sql = 'SELECT memberid, ts FROM group_member_t WHERE groupid = '.$group_id.';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        $result_array = $query->result_array();

        foreach($result_array as $row)
        {
            $insert_sql = 'INSERT INTO winner_t (memberid, ts) SELECT * FROM (SELECT '.$this->db->escape($row['memberid']).', '.$this->db->escape($row['ts']).') AS tmp WHERE NOT EXISTS(SELECT id FROM winner_t WHERE memberid = '.$this->db->escape($row['memberid']).');'; 
            // log
            // echo $insert_sql.'<br>';
            $this->db->query($insert_sql);
        }
    }

    /* 每个人只能领一次奖 */
    public function get_winners($start_id, $count)
    {
        // $query_sql = 'SELECT id, memberid, ts FROM winner_t WHERE id > '.$start_id.' LIMIT '.$count.';';
        $query_sql = 'SELECT id, memberid FROM winner_t WHERE id > '.$start_id.' LIMIT '.$count.';';
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);

        return $query->result_array();
    }

    public function get_user_info_total($openid)
    {
        $query_sql = 'SELECT openid, nickname, sex, headimgurl, province, city, country FROM user_t WHERE openid = '.$this->db->escape($openid).' LIMIT 1;';
        return $this->get_user_info($query_sql); 
    }

    public function get_user_info_min($openid)
    {
        $query_sql = 'SELECT openid AS uid, nickname AS n, sex AS s, headimgurl AS h FROM user_t WHERE openid = '.$this->db->escape($openid).' LIMIT 1;';
        return $this->get_user_info($query_sql); 
    }
    
    public function get_user_info($query_sql)
    {
        // echo $query_sql.'<br>';
        $query = $this->db->query($query_sql);
        return $query->row_array(); 
    }

    public function add_create_group_count($openid)
    {
        $query_sql = 'UPDATE user_t SET create_group_count = create_group_count + 1 WHERE openid = '.$this->db->escape($openid).';';
        // echo $query_sql.'<br>';
        $this->db->query($query_sql);
        return $this->db->affected_rows(); 
    }

    public function add_join_group_count($openid)
    {
        $query_sql = 'UPDATE user_t SET join_group_count = join_group_count + 1 WHERE openid = '.$this->db->escape($openid).';';
        // echo $query_sql.'<br>';
        $this->db->query($query_sql);
        return $this->db->affected_rows(); 
    }
}

