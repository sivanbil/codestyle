const ENCRYPT = require('md5.js');
// request gateway url
const URL = 'https://xxx.xx.xx/xxx/xxxxxx';
// request encrypt key
const MD5STR = 'xxxxxxxxxx';

class req {

  // 返回数据
  _res;
  // request params
  _path;
  _sign;
  _data;

  constructor(path, data) {
    this._data = data;
    this._path = path;
    this._sign = this._encrypt();
  }

  doRequest(callbackparams) {
    console.log({
      //data: JSON.stringify(this._data.data),
      path: this._path,
      //sign: this._sign
    });
    wx.request({
      url: URL,
      data: {
        data: JSON.stringify(this._data.data),
        path: this._path,
        sign: this._sign
      },
      method: 'POST',
      success: function (res) {
        if (callbackparams.successCall != undefined) {
          callbackparams.successCall.call({}, res);
        }
      },
      fail: function (res) {
        if (callbackparams.failCall != undefined) {
          callbackparams.failCall.apply(this, res);
        }
      },
      complete: function () {

      }
    })
    return true;
  }

  _encrypt() {
    var encryptstr = ENCRYPT.md5(this._data['data']['sysname'] + this._data['data']['username'] + ENCRYPT.md5(JSON.stringify(this._data['data']['request_data'])) + MD5STR);

    return encryptstr;

  }
}

const fetchData = (path, data, callbackparams) => {
  let obj = new req(path, data);
  return obj.doRequest(callbackparams)
}

const myencodeURI =  (str)  => {
  return encodeURIComponent(encodeURIComponent(str));
}

module.exports = {
  fetchData: fetchData,
  myencodeURI: myencodeURI
}
