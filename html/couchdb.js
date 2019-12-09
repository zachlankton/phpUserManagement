let request = obj => {
    return new Promise((resolve, reject) => {
        let xhr = new XMLHttpRequest();
        xhr.open(obj.method || "GET", obj.url);
        xhr.withCredentials = true;
        if (obj.headers) {
            Object.keys(obj.headers).forEach(key => {
                xhr.setRequestHeader(key, obj.headers[key]);
            });
        }
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                var cType = xhr.getResponseHeader("content-type");
                if (cType == "application/json") {
                    resolve(JSON.parse(xhr.response));
                }
                else {
                    resolve(xhr.response);
                }
            }
            else {
                reject(xhr.statusText);
            }
        };
        xhr.onerror = () => reject(xhr.statusText);
        xhr.send(obj.body);
    });
};

function serialize( obj ) {
    if (obj == null || obj == undefined){return ""}
    return Object.keys(obj).reduce(function(a,k){a.push(k+'='+encodeURIComponent(obj[k]));return a},[]).join('&')
}

async function get(url, body){
    body = JSON.stringify(body);
    return await request({
        url,
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        },
        body
        
    });
}

async function put(url, body){
    body = JSON.stringify(body);
    return await request({
        url,
        method: "PUT",
        headers: {
            "Content-Type": "application/json"
        },
        body
        
    });
}

async function post(url, body){
    body = JSON.stringify(body);
    return await request({
        url,
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body
        
    });
}

async function del(url, body){
    body = JSON.stringify(body);
    return await request({
        url,
        method: "DELETE",
        headers: {
            "Content-Type": "application/json"
        },
        body
        
    });
}

function couchdb(params){
    // this function is for individual databases;
    
    let url = params.url;
    
    
    this.all_docs = async function(options){
        // https://docs.couchdb.org/en/master/api/ddoc/views.html#api-ddoc-view
        return await get(url + "/_all_docs?" + serialize( options ) ).catch(function(err){
            return err;
        });
    }

    this.get_design_doc_view = async function(designDoc, viewName, options){
        return await get(url + "/_design/" + designDoc + "/_view/" + viewName + "?"
         + serialize( options ) ).catch(function(err){
            return err;
        });
    }
    
    this.bulk_docs = async function(options){
        return await post(url + "/_bulk_docs", options);
    }
    
    this.bulk_get = async function(options){
        return await post(url + "/_bulk_get", options);
    }
    
    this.compact = async function(options){
        return await post(url + "/_compact", options);
    }
    
    this.getSecurity = async function(){
        return await get(url + "/_security");
    }
    
    this.putSecurity = async function(options){
        return await put(url + "/_security", options);
    }
    
    this.get = async function(docid, options){
        return await get(url + "/" + docid + "?" + serialize(options));
    }
    
    this.put = async function(doc){
        return await this.bulk_docs({docs:[doc]});
    }
    
    this.delete = async function(doc){
        var docid = doc._id;
        var rev = doc._rev;
        return await del(url + "/" + docid + "?rev=" + rev );
    }
    
    this.find = async function(options){
        // https://docs.couchdb.org/en/master/api/database/find.html
        let defaultOptions = {
            limit: 99999999999999999999
        }
        Object.assign(defaultOptions, options);

        let find = await post(url + "/_find", options);
        return find;
    }
    
    this.explain = async function(options){
        // https://docs.couchdb.org/en/master/api/database/find.html#db-explain
        let defaultOptions = {
            limit: 99999999999999999999
        }
        Object.assign(defaultOptions, options);
        return await post(url + "/_explain", options);
    }
    
    this.info = async function(){
        return await get(url);
    }
    
    var changeSource;
    var changesHeartbeat;
    
    this.changes = function(callback, options){
        if (changeSource != undefined && changeSource.readyState != 2){return changeSource;}
        
        let defaultOptions = {
            feed: "eventsource",
            heartbeat: true,
            since: "now",
            include_docs: true,
            conflicts: true
        }
        Object.assign(defaultOptions, options);
        
        changeSource = new EventSource(url + "/_changes?" + serialize( defaultOptions ), {withCredentials: true});
        
        changeSource.onerror = function(e) {
            console.log(url, 'changes feed failed.');
        };
        
        callback = callback || function(d){
            console.log(d);
        }
        
        var sourceListener = function(e) {
            callback(JSON.parse(e.data))
        };
        
        // start listening for events
        changeSource.addEventListener('message', sourceListener, false);
        
        function setupTimeout(){
            changesHeartbeat = setTimeout(function(){
                console.log(url, "heartbeat is missing!")
            }, 120000);
        }
        
        changeSource.addEventListener('heartbeat', function (e) {
            clearTimeout(changesHeartbeat);
            setupTimeout();
            console.log(Date.now(), "heartbeat");
        }, false)
        
    }
    
}


function couch(params){
    //this function is for entire installations of couch
    
    let url = params.url;
    let mThis = this;
    
    this.checkConnection = async function() {
        let dbInfo = await get( url );
        return dbInfo;
    }
    
    this.listDBs = async function() {
        let dbs = await get(url + "_all_dbs" );
        return dbs;
    }
    
    this.getAdmins = async function(){
        let admins =  await get( url + "_node/_local/_config/admins" );
        return admins;
    }
    
    this.createAdmin = async function(name, pw){
        let admins =  await put( url + "_node/_local/_config/admins/"+name, pw );
        return admins;
    }
    
    this.deleteAdmin = async function(name){
        let admins =  await del( url + "_node/_local/_config/admins/"+name );
        return admins;
    }
    
    this.getUsers = async function(){
        //let users = await get( url + "_users/_all_docs?include_docs=true&start_key=%22org.couchdb.user%22" );
        let options = {
            selector: {"_id":  {"$gte": "org.couchdb.user"}  },
            limit: 99999999999999999999
        }
        let users = await mThis._users.find(options);
        return users;
    }

    this.getUser = async function(name) {
        let user = await get( url + "_users/org.couchdb.user:" + name.toLocaleLowerCase() )
        return user
    }
    
    this.addUser = async function(name, pw){}
    
    this.deleteUser = async function(name){}
    
    this.updateUserPassword = async function(name, pw){}
    
    this.login = async function(name, password) {
        let login = post(url+"_session", {name, password})
        .catch(function (err) {
            console.log(err);
        });
        
        return login;
    }
    
    this.getSession = async function(){
        let session = get(url+"_session");
        return session;
    }
    
    this.logout = async function(){
        let session = del(url+"_session");
        return session;
    }
    
    async function setup(){
        let dbs = await mThis.listDBs();
        dbLen = dbs.length;
        
        for (var i=0; i<dbLen; i++){
            let db = dbs[i];
            mThis[db] = new couchdb({url: url+db});
        }
    
    }

    setup();
   
}