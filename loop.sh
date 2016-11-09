#!/usr/bin/env bash

redis-cli set Giveaway:error 0
while true; do 
  php adjustOffset.php
  ERROR=$(redis-cli get Giveaway:error)
  while [ $ERROR -eq 1 ]; do
    redis-cli incr Giveaway:offset
    php adjustOffset.php
  done
  php getUpdates.php
done
