This repository contains necessary changes to Zabbix Server v3.4.4 to implement DUO two-factor authentication (https://duo.com/docs/duoweb). You can see all the diffs here so probably will be able to apply to other Zabbix Server versions too.
 
* How to use
1) Zabbix Server needs to be installed and fully functional (see https://www.zabbix.com/documentation/3.4/manual/installation/install)
2) Apply changes to DB:
```
mysql -u zabbix -pe<your_password> -h 127.0.0.1 zabbix < config.myql
```
3) Copy all the files (alternatively copy only new files and changed files, you can find these looking at commits history) from zabbix-3.4.4/frontends/php/ to php/ folder of your installation

That is it. Now refresh your WebUI and go to Administration->Authentication, you should see new Tab '2FA', the rest of options are self explanatory. Enable DUO, provide necessary parameters and after that all the users after they are successfully authorized by 'Intermal' authentication will be requested to go through 2FA.

* Use on your own risk. No responsibility assumed.

* Happy to receive feedback.
