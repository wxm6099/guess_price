#!/bin/bash

#/*
# * filename      : create_guess_price_db.sh
# * descriptor    : 
# * author        : Kevin    
# * create time   : 2015-11-18
# * modify list   :
# * +----------------+---------------+---------------------------+
# * | date           | who           | modify summary            |
# * +----------------+---------------+---------------------------+
# */


database_name="guess_price_d"
MYSQL="/usr/local/mysql/bin/mysql -uroot1 -pMJ3am9yxJj2I6srrCg --default-character-set=utf8mb4 "

function drop_db()
{
    drop_db_sql="DROP DATABASE ${database_name}"
    ${MYSQL} -e "${drop_db_sql}"
}

function create_db()
{
    create_db_sql="CREATE DATABASE IF NOT EXISTS ${database_name};"
    ${MYSQL} -e "${create_db_sql}"
}

function create_user_table()
{
    table_name="user_t"
    create_table_sql="CREATE TABLE IF NOT EXISTS ${table_name}(
        openid VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'wx openid',
        nickname VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'nickname',
        sex TINYINT(2) NOT NULL DEFAULT 0 COMMENT '0:not know, 1:male, 2:female',
        province VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'province',
        city VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'city',
        country VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'country',
        headimgurl VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'head image url',
        privilege VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'privilege: json array',
        unionid VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'may not exist',
        access_token VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'access_token',
        create_group_count INT(11) NOT NULL DEFAULT 0 COMMENT 'create group count',
        join_group_count INT(11) NOT NULL DEFAULT 0 COMMENT 'join group count',
        address VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'detailed address',
        name VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'real name',
        phone VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'phone',
        is_removed TINYINT(2) NOT NULL DEFAULT '0' COMMENT '0: No, 1: Yes', 
        ts TIMESTAMP DEFAULT NOW() COMMENT 'insert timestamp',

        PRIMARY KEY(openid),
        INDEX(create_group_count),
        INDEX(join_group_count),
        INDEX(is_removed)
        ) ENGINE=MYISAM DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

    ${MYSQL} ${database_name} -e "${create_table_sql}"
    echo "create table "${table_name}
}

function create_group_table()
{
    table_name="group_t"
    create_table_sql="CREATE TABLE IF NOT EXISTS ${table_name}(
        id BIGINT(64) NOT NULL DEFAULT 0 COMMENT 'unique id',
        leaderid VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'leader openid',
        member_count INT(11) NOT NULL DEFAULT 0 COMMENT 'member count',
        is_price_right TINYINT(2) NOT NULL DEFAULT 0 COMMENT '0:No, 1:Yes',
        ts TIMESTAMP DEFAULT NOW() COMMENT 'insert timestamp', 

        PRIMARY KEY(id),
        INDEX(leaderid),
        INDEX(member_count),
        INDEX(is_price_right)
        ) ENGINE=MYISAM DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ${MYSQL} ${database_name} -e "${create_table_sql}"
    echo "create table "${table_name}
}

function create_group_id_table()
{
    table_name="group_id_t"
    create_table_sql="CREATE TABLE IF NOT EXISTS ${table_name}(
        id BIGINT(64) NOT NULL AUTO_INCREMENT COMMENT 'group id',
        extra char(1) NOT NULL DEFAULT '' COMMENT 'extra data',

        PRIMARY KEY(id),
        UNIQUE KEY extra(extra)
        ) ENGINE=MYISAM DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ${MYSQL} ${database_name} -e "${create_table_sql}"
    echo "create table "${table_name}
}

function create_group_member_table()
{
    table_name="group_member_t"
    create_table_sql="CREATE TABLE IF NOT EXISTS ${table_name}(
        id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'auto increment id',
        groupid BIGINT(64) NOT NULL DEFAULT 0 COMMENT 'group id',
        memberid VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'member openid',
        price DOUBLE(16, 2) NOT NULL DEFAULT 0 COMMENT 'price guessed',
        ts TIMESTAMP DEFAULT NOW() COMMENT 'insert timestamp', 

        PRIMARY KEY(id),
        INDEX(groupid),
        INDEX(memberid)
        ) ENGINE=MYISAM DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ${MYSQL} ${database_name} -e "${create_table_sql}"
}

function create_winner_table()
{
    table_name="winner_t"
    create_table_sql="CREATE TABLE IF NOT EXISTS ${table_name}(
        id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'auto increment id',
        memberid VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'member openid',
        ts TIMESTAMP DEFAULT NOW() COMMENT 'insert timestamp', 

        PRIMARY KEY(id),
        INDEX(memberid)
        ) ENGINE=MYISAM DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ${MYSQL} ${database_name} -e "${create_table_sql}"
}

create_db
create_user_table
create_group_table
create_group_id_table
create_group_member_table
create_winner_table
