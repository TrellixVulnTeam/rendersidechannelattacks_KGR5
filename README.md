# Rendering Contention Channel Attacks
We design a novelty rendering contentionchannel. Specifically, we stress the browser’s rendering re-source with a stable, self-adjustable WebGL program and mea-sure the time taken to render a sequence of frames. The mea-sured time sequence is further used to infer any co-renderingevent of the browser.  
To demonstrate the channel’s feasibility, we design and im-plement a prototype, open-source framework, calledSIDER,to launch four attacks using the rendering contention channel,which are (i) cross-browser, cross-mode cookie synchronization, (ii) history sniffing, (iii) website fingerprinting, and (iv)keystroke logging.  
## Environment  
We deploy this project on Apache2 + Flask. We also show the demo on http://renderingsidechannelattacks.com:8080/ . 
## Deploy(Ubuntu Apache2 + Flask)
### install mod_wsgi
`sudo apt-get install libapache2-mod-wsgi python-dev`   
`sudo a2enmod wsgi  `  
### install Flask
`sudo apt-get install python-pip  `
#### we need use virtual environment  
`sudo pip install virtualenv `  
`source venv/bin/activate  `   
`sudo pip install Flask  `  
`sudo python __init__.py `  
### create apache
`sudo nano /etc/apache2/sites-available/html2markdown.conf `   
Also we need change the ServerName, WSGIScriptAlia and PATH  
#### start virtual host
`sudo a2ensite html2markdown  `  
`sudo service apache2 restart  `  

## Deploy(Ubuntu PHP-Proxy)
Put `php-proxy-app/` into your default php deault diretory.   

Start php `sudo /usr/sbin/apachectl start`.
## Code repository
We have different versions code for collecting different data and we will show one version.  
rendersidechannelattacks/FlaskApp/FlaskApp/templates/ is for all html files and rendersidechannelattacks/FlaskApp/FlaskApp/static/ us for others like js files.  

### Proxy Server(escape option)
1. Open http://localhost/php-proxy-app/ in browser.
2. Input the address of the object website in the box, remember to include https if necessary. The default prefix is http.  

### Cross-browser, cross-mode cookie synchronization
1. Open http://localhost/senddatapre/ and input the send message.  
2. Open http://localhost/receivedata/ in a new window.

### History sniffing attack
In our basic collect version. The html file is rendersidechannelattacks/FlaskApp/FlaskApp/templates/HSA/aquarium.html   
and js file is rendersidechannelattacks/FlaskApp/FlaskApp/static/HSA/aquarium.js /.
1. Open rendersidechannelattacks/FlaskApp/FlaskApp/static/HSA/aquarium.js /  and input the website you want to test in line 1838.
2. Run http://localhost/test/.    


Functionality | Code | Description
------------ | -------------| -------------
Initialization | 1855-1876 | For different devices, give a similar workload.
Data Collection | 1890-1980 | Collecting data.
Denoising | 2042-2084 | Algorithm 1 Denoising.
Outlier Detection | 2094-2101 | Algorithm 2 Max-min Outlier Detection
DTW-M | 2127-2155 | Algorithm DTW

### Website fingerprinting attack
Share Initialization, Data Collection and Denoising part with History sniffing.
1. Add test website lit in rendersidechannelattacks/FlaskApp/FlaskApp/static/WFA/aquarium_Collect.js /
2. Open http://localhost/collectdataNC/ to collect no cache data.
3. Open http://localhost/collectdataAC/ to collect no cache data.

### Keystroke logging attack
#### Prerequisite
`npm install robotjs`

#### Procedure
1. modify in `ks_collect_data.js` line 107 to switch to the data file you like
2. close all chrome windows
3. open <http://localhost/gpu_attack.github.io/aquarium/aquarium.html> in a new chrome window
4. open <http://www.google.com/> in a new chrome window
5. select search box
6. run “node ks_collect_data.js”
7. switch to google search box. 
8. get the data

#### Data

local: [timestamp] list, [key] list.

server: [timestamp, how many seconds each frame takes up] list.
