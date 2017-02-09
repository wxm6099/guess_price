<?php

$common_file = APPPATH.'controllers/common.php';
if(file_exists($common_file))
{
    require_once($common_file);
}
else
{
    print($common_file.' not exists.');
}

$cache_lock_file = APPPATH.'controllers/CacheLock.php';
if (file_exists($cache_lock_file))
{
    require_once($cache_lock_file);
}
else
{
    print($cache_lock_file.' not exists.');
}

class GuessPrice extends CI_Controller {
    private static $config_array;
    private static $cache_lock;

    private static $set_wx_code_resp_id = 1002;
    private static $set_new_group_resp_id = 1004;
    private static $get_group_member_resp_id = 1006;
    private static $join_group_resp_id = 1008;
    private static $check_is_winning_resp_id = 1010;
    private static $set_express_info_resp_id = 1012;
    private static $get_winner_list_resp_id = 1014;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('PHPRequests');
        $this->load->model('GuessPrice_model');

        self::$config_array = get_gp_config();
        # TODO: debug
        /*
        foreach(self::$config_array as $key => $value)
        {
            echo 'config: '.$key.' => '.$value.'<br>';
        }

        echo '<br>';
        */

        $join_group_lock_name = 'join_group_lock';
        # TODO: modify online
        # $join_group_lock_path = APPPATH.'../guessprice/'; 
        $join_group_lock_path = '/tmp/'; 
        self::$cache_lock = new CacheLock($join_group_lock_name, $join_group_lock_path);
    }

    # 1001
    public function set_wx_code($wx_code, $group_id)
    {
        if (NULL == $wx_code)
        {
            $resp_array = array('id' => self::$set_wx_code_resp_id, 'uid' => 0);
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        }

        $ret_array = $this->request_wx_user_info($wx_code);

        if (NULL == $ret_array)
        {
            $resp_array = array('id' => self::$set_wx_code_resp_id, 'uid' => 0);
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        } 

        $info_json = $ret_array['info_json'];
        $token = $ret_array['token'];
        $info_array = array('openid' => $info_json->{'openid'},
                        'nickname' => $info_json->{'nickname'}, 
                        'sex' => $info_json->{'sex'}, 
                        'headimgurl' => $info_json->{'headimgurl'}, 
                        'province' => $info_json->{'province'}, 
                        'city' => $info_json->{'city'}, 
                        'country' => $info_json->{'country'});
        $this->GuessPrice_model->set_wx_user_info($token, $info_json);
        echo $this->merge_user_info($group_id, $info_array);
    }

    public function login($openid, $group_id)
    {
        if (NULL == $openid || NULL == $group_id)
        {
            $resp_array = array('id' => self::$set_wx_code_resp_id, 'uid' => 0);
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        }

        $info_array = $this->GuessPrice_model->get_user_info_total($openid);
        echo $this->merge_user_info($group_id, $info_array);
    }

    public function merge_user_info($group_id, $info_array)
    {
        $can_create_group = $this->GuessPrice_model->can_create_group($info_array['openid']);
        // echo 'can_create_group: '.$can_create_group.'<br>';
        $can_join_group = $this->GuessPrice_model->can_join_group($info_array['openid']);
        // echo 'can_join_group: '.$can_join_group.'<br>';
        $is_game_over = $this->GuessPrice_model->is_game_over();
        // echo 'is_game_over: '.$is_game_over.'<br>';

        // no groupid parma in url
        if (0 == $group_id)
        {
            $group_id = $this->GuessPrice_model->get_groupid_created($info_array['openid']);
        }

        $member_info_array = array();

        if (0 < $group_id)
        {
            $member_info_array = $this->get_member_info($group_id);
        }
        else
        {
            // make new group
            $group_id = $this->GuessPrice_model->make_group_id();

            // make new group error
            if (0 == $group_id)
            {
                $resp_array = array('id' => self::$set_wx_code_resp_id, 'uid' => 0);
                $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
                echo $resp_data;

                return;
            }
        }

        if (1 == $is_game_over)
        {
            $can_create_group = 0;
            $can_join_group = 0;
            $group_id = 0;
        }

        // echo 'group_id: '.$group_id.'<br>';
        $resp_array = array('id' => self::$set_wx_code_resp_id,
                        'uid' => $info_array['openid'],
                        'n' => $info_array['nickname'],
                        's' => $info_array['sex'],
                        'pro' => $info_array['province'],
                        'city' => $info_array['city'],
                        'cou' => $info_array['country'],
                        'h' => $this->adjustHeadImgUrl($info_array['headimgurl']),
                        'can_create_group' => $can_create_group,
                        'can_join_group' => $can_join_group,
                        'is_game_over' => $is_game_over,
                        'gid' => $group_id,
                        'm' => $member_info_array); 
        $resp_data = $this->to_json($resp_array);

        return $resp_data;
    }

    public function request_wx_user_info($wx_code)
    {
        // echo $wx_code.'<br>';
        $token_openid_array = $this->get_wx_access_token($wx_code); 
        // echo $token_openid_array[0].'<br>';
        // echo $token_openid_array[1].'<br>';
        $wx_user_info = $this->get_wx_user_info($token_openid_array[0], $token_openid_array[1]);

        if (NULL == $wx_user_info)
        {
            return NULL;
        }

        # TODO: debug
        // $wx_user_info = '{"openid":"o3xSes_Yr9MresBkAwBAbfwlMVQk","nickname":"测试名","sex":1,"language":"zh_CN","city":"深圳","province":"广东","country":"中国","headimgurl":"http:\/\/wx.qlogo.cn\/mmopen\/tqRiaNianNl1l0iclIFLuEBRF8ZAosiavTRluqcbRHoJpLprcWeTkbyeeBV7iaSgq7jHmkk83aBIchBdYdtzPtDYJ7KzgNibNnXDc1\/0","privilege":[]}';
        // echo $wx_user_info.'<br>';
        
        /*
        # TODO: test
        $res_array = $this->GuessPrice_model->get_group_id();
        # travel array
        foreach($res_array as $row)
        {
            // echo $row['id'];
            // echo $row['extra'];
        }
        */

        // echo '<br><br>';
        $info_json = json_decode($wx_user_info);

        return array('info_json' => $info_json, 'token' => $token_openid_array[0]);
    }

    # 1003
    public function set_new_group($openid, $group_id, $guess_price)
    {
        # TODO: check guess_price's range
        if (NULL == $openid || NULL == $group_id || NULL == $guess_price)
        {
            $resp_array = array('id' => self::$set_new_group_resp_id, 'r' => 0, 'rea' => 2);
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;
            return;
        }

        $is_game_over = $this->GuessPrice_model->is_game_over();
        // echo 'is_game_over: '.$is_game_over.'<br>';

        if (1 == $is_game_over)
        {
            $resp_array = array('id' => self::$set_new_group_resp_id, 'r' => 0, 'rea' => 1); 
        }
        else
        {
            $is_price_right = (self::$config_array['right_price'] == $guess_price ? 1 : 0); 
            $ret = $this->GuessPrice_model->set_new_group($openid, $group_id, $is_price_right);    
            if (0 == $ret)
            {
                $rea = 2;
            }
            else
            {
                $ret_set_group_member = $this->GuessPrice_model->set_group_member($openid, $group_id, $guess_price);

                if ($ret_set_group_member)
                {
                    $rea = 0;
                    $this->GuessPrice_model->add_create_group_count($openid);
                }
                else
                {
                    $ret = 0;
                    $rea = 3;
                }
            }

            $resp_array = array('id' => self::$set_new_group_resp_id, 'r' => $ret, 'rea' => $rea); 
        }

        $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
        echo $resp_data;
    }

    # 1005
    public function get_group_member($openid, $group_id)
    {
        if (NULL == $openid || NULL == $group_id)
        {
            $resp_array = array('id' => self::$get_group_member_resp_id, 'r' => 0, 'rea' => 1);        
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        } 

        $member_info_array = $this->get_member_info($group_id);

        if (0 < count($member_info_array))
        {
            $resp_array = array('id' => self::$get_group_member_resp_id, 'r' => 1, 'rea' => 0, 'm' => $member_info_array);
        }
        else
        {
            $resp_array = array('id' => self::$get_group_member_resp_id, 'r' => 0, 'rea' => 2);
            echo 'get user info error.<br>';
        }

        $resp_data = $this->to_json($resp_array);
        echo 'resp_data: '.$resp_data.'<br>';
    }

    public function get_member_info($group_id)
    {
        $group_leaderid = $this->GuessPrice_model->get_group_leaderid($group_id);
        // echo 'group_leaderid: '.$group_leaderid.'<br>';

        $group_memberid_array = $this->GuessPrice_model->get_group_memberid($group_id);
        $member_info_array = array();
        // echo 'member_count: '.count($group_memberid_array).'<br>';

        foreach($group_memberid_array as $row)
        {
            // echo $row['memberid'].' => '.$row['price'].'<br>';
            $user_info_array = $this->GuessPrice_model->get_user_info_min($row['memberid']); 

            if ($group_leaderid == $user_info_array['uid'])
            {
                $user_info_array['l'] = 1;
            }
            else
            {
                $user_info_array['l'] = 0;
            }

            $user_info_array['h'] = $this->adjustHeadImgUrl($user_info_array['h']);
            $user_info_array['p'] = doubleval($row['price']);
            // echo $user_info_array['n'].'<br>';
            // echo $user_info_array['l'].'<br>';
            array_push($member_info_array, $user_info_array);
        }
        
        return $member_info_array;
    }

    public function adjustHeadImgUrl($imgUrl)
    {
        if (!empty($imgUrl) && '0' == substr($imgUrl, -1))
        {
            $preUrl = substr($imgUrl, 0, strlen($imgUrl) - 1);

            return $preUrl.'64';
        }
        else
        {
            return $imgUrl;
        }
    }

    # 1007
    public function join_group($openid, $group_id, $guess_price)
    {
        if (NULL == $openid || NULL == $group_id || NULL == $guess_price)
        {
            $resp_array = array('id' => self::$join_group_resp_id, 'r' => 0, 'rea' => 1);
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        }

        $error = false;
        $group_member_count = 0;

        /* 活动结束 */
        $is_game_over = $this->GuessPrice_model->is_game_over();
        // echo 'is_game_over: '.$is_game_over.'<br>';

        if (1 == $is_game_over)
        {
            $resp_array = array('id' => self::$join_group_resp_id, 'r' => 0, 'rea' => 3); 
            $error = true;
        }
        else
        {
            // lock
            self::$cache_lock->lock();
            /* 参团次数用完 */
            $can_join_group = $this->GuessPrice_model->can_join_group($openid);
            // echo 'can_join_group: '.$can_join_group.'<br>';

            if (0 == $can_join_group) 
            {
                $resp_array = array('id' => self::$join_group_resp_id, 'r' => 0, 'rea' => 4); 
                $error = true;
                // unlock
                self::$cache_lock->unlock();
            }
            else
            {
                /* 团是否满员 */
                $group_member_count = $this->GuessPrice_model->get_group_member_count($group_id);
            
                if (-1 == $group_member_count || self::$config_array['group_member_max_count'] <= $group_member_count)
                {
                    $resp_array = array('id' => self::$join_group_resp_id, 'r' => 0, 'rea' => 2); 
                    $error = true;
                    // unlock
                    self::$cache_lock->unlock();
                }
            }
        }

        if (!$error)
        {
            $ret = $this->GuessPrice_model->set_group_member($openid, $group_id, $guess_price);

            if ($ret)
            {
                $this->GuessPrice_model->add_join_group_count($openid);
                $is_price_right = (self::$config_array['right_price'] == $guess_price ? 1 : 0); 
                $this->GuessPrice_model->update_group_info($group_id, $is_price_right);

                $can_check_winning = 0;
                $is_winning = 0;

                if (self::$config_array['group_member_max_count'] <= ($group_member_count + 1)) 
                {
                    $can_check_winning = 1;
                    $is_winning = $this->GuessPrice_model->get_group_winning_state($group_id);
                    if (1 == $is_winning)
                    {
                        $this->GuessPrice_model->add_winner($group_id);
                    }
                }

                $resp_array = array('id' => self::$join_group_resp_id, 'r' => 1, 'rea' => 0, 'w' => $is_winning); 
            }
            else
            {
                $resp_array = array('id' => self::$join_group_resp_id, 'r' => 0, 'rea' => 5); 
            }

            // unlock
            self::$cache_lock->unlock();
        }

        $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT); 
        echo $resp_data;
    }

    public function add_winner_test($group_id)
    {
        $this->GuessPrice_model->add_winner($group_id);
    }

    # 1009
    public function check_is_winning($openid, $group_id)
    {
        if (NULL == $openid || NULL == $group_id)
        {
            $resp_array = array('id' => self::$check_is_winning_resp_id, 'r' => 0, 'rea' => 1);
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT); 
            echo $resp_data;

            return;
        }

        $is_price_right = $this->GuessPrice_model->get_group_winning_state($group_id);

        if (1 == $is_price_right)
        {
            $resp_array = array('id' => self::$check_is_winning_resp_id, 'r' => 1, 'rea' => 0);
        }
        else if (0 == $is_price_right)
        {
            $resp_array = array('id' => self::$check_is_winning_resp_id, 'r' => 0, 'rea' => 0);
        }
        else
        {
            $resp_array = array('id' => self::$check_is_winning_resp_id, 'r' => 0, 'rea' => 2);
        }
        
        $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
        echo $resp_data;
    }

    # 1011
    public function set_express_info($openid, $group_id)
    {
        if (NULL == $openid || NULL == $group_id)
        {
            $resp_array = array('id' => self::$set_express_info_resp_id, 'r' => 0, 'rea' => 1); 
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        }

        $name = $this->input->post('n'); 
        $address = $this->input->post('a');
        $phone = $this->input->post('p');

        if (NULL == $name || NULL == $address || NULL == $phone)
        {
            $resp_array = array('id' => self::$set_express_info_resp_id, 'r' => 0, 'rea' => 1); 
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;

            return;
        }

        $ret = $this->GuessPrice_model->set_express_info($openid, $name, $address, $phone);
        if (1 == $ret)
        {
           $resp_array = array('id' => self::$set_express_info_resp_id, 'r' => 1, 'rea' => 0); 
        }
        else
        {
            $resp_array = array('id' => self::$set_express_info_resp_id, 'r' => 0, 'rea' => 2);
        }
    
        $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
        echo $resp_data;
    }

    public function get_winner_list($openid, $group_id, $start_id, $count)
    {
        if (NULL == $openid || NULL == $group_id || NULL == $start_id || NULL == $count)
        {
            $resp_array = array('id' => self::$get_winner_list_resp_id, 'r' => 0, 'rea' => 1);        
            $resp_data = json_encode($resp_array, JSON_FORCE_OBJECT);
            echo $resp_data;
            
            return;
        }

        # no page turning
        $count = self::$config_array['total_prize_count'];
        $winner_id_array = $this->GuessPrice_model->get_winners($start_id, $count);
        $winner_array = array();

        foreach($winner_id_array as $row)
        {
            // echo $row['memberid'].'<br>';
            // echo $row['ts'].'<br>'; 
            $user_info_array = $this->GuessPrice_model->get_user_info_min($row['memberid']); 

            if ($openid == $user_info_array['uid'])
            {
                $user_info_array['m'] = 1;
            } 

            // $user_info_array['h'] = $this->adjustHeadImgUrl($user_info_array['h']);
            unset($user_info_array['h']);
            unset($user_info_array['uid']);
            unset($user_info_array['s']);
            // echo $user_info_array['n'].'<br>';
            $user_info_array['id'] = $row['id'];
            // $user_info_array['ts'] = $row['ts'];
            array_push($winner_array, $user_info_array);
        }

        if (0 < count($winner_array))
        {
            $resp_array = array('id' => self::$get_winner_list_resp_id, 'r' => 1, 'rea' => 0, 'w' => $winner_array);
        }
        else
        {
            $resp_array = array('id' => self::$get_winner_list_resp_id, 'r' => 0, 'rea' => 2);
            // echo 'get user info2 error.<br>';
        }

        $resp_data = $this->to_json($resp_array);
        echo $resp_data;
    }

    private function get_wx_access_token($wx_code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxbb5f8f68dfc90d6c&secret=5ef71993c7b0019653432c3c65bde110&code='.$wx_code.'&grant_type=authorization_code';
        // echo $url;
        $options = array('timeout' => 30);
        $token_resp = Requests::get($url, array(), $options);
        $token_resp_json = json_decode($token_resp->body);

        if (property_exists($token_resp_json, 'access_token'))
        {
            $access_token = $token_resp_json->{'access_token'};
        }
        else
        {
            $access_token = NULL;
        }
        
        if (property_exists($token_resp_json, 'openid'))
        {
            $openid = $token_resp_json->{'openid'};
        }
        else
        {
            $openid = NULL;
        }

        return array($access_token, $openid);
    }

    private function get_wx_user_info($access_token, $openid)
    {
        if (NULL == $access_token || NULL == $openid)
        {
            return NULL;
        }

        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        // echo 'user_info_url: '.$url.'<br>';
        $options = array('timeout' => 30);
        $info_resp = Requests::get($url, array(), $options);
        return $info_resp->body;
    }

    // not used
    /**
     * @param array      $array
     * @param int|string $position
     * @param mixed      $insert
     */
    private function array_insert(&$array, $position, $insert)
    {
        if (is_int($position)) 
        {
            array_splice($array, $position, 0, $insert);
        } 
        else 
        {
            $pos = array_search($position, array_keys($array));
            $array = array_merge(
                    array_slice($array, 0, $pos),
                    $insert,
                    array_slice($array, $pos)
                    );
        }
    }

    /**************************************************************
     *
     *  使用特定function对数组中所有元素做处理
     *  @param  string  &$array     要处理的字符串
     *  @param  string  $function   要执行的函数
     *  @return boolean $apply_to_keys_also     是否也应用到key上
     *  @access private
     *
     *************************************************************/
    private function array_recursive(&$array, $function, $apply_to_keys_also = false)
    {
        static $recursive_counter = 0;
        if (++$recursive_counter > 1000) 
        {
            die('possible deep recursion attack');
        }

        foreach ($array as $key => $value) 
        {
            if (is_array($value)) 
            {
                $this->array_recursive($array[$key], $function, $apply_to_keys_also);
            } 
            else 
            {
                $array[$key] = $function($value);
            }

            if ($apply_to_keys_also && is_string($key)) 
            {
                $new_key = $function($key);

                if ($new_key != $key) 
                {
                    $array[$new_key] = $array[$key];
                    unset($array[$key]);
                }
            }
        }

        $recursive_counter--;
    }

    /**************************************************************
     *
     *  将数组转换为JSON字符串（兼容中文）
     *  @param  array   $array      要转换的数组
     *  @return string      转换得到的json字符串
     *  @access private
     *
     *************************************************************/
    private function to_json($array_in) 
    {
        $this->array_recursive($array_in, 'urlencode', true);
        // if use JSON_FORCE_OBJECT, it will turn array to '{0:{}, 1:{}}'.
        $json = json_encode($array_in);

        return urldecode($json);
    }
}
