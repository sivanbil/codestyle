const wxreq = require("xx/xx/xx/request.js");
const app = getApp();
Page({

  /**
   * 页面的初始数据
   */
  data: {
    connectstartupid: null,
    /*error start */
    usernameWithoutError: true,
    mobileWithoutError: true,
    demandWithoutError: true,
    companyWithoutError: true,
    /*error msg*/
    emptyErrorMsg: '未填写',
    usernameErrorMsg: '',
    mobileErrorMsg: '',
    demandErrorMsg: '',
    companyErrorMsg: '',
    mobileWithoutError: true,
    demandWithoutError: true,
    /*focus start*/
    usernameFocused: false,
    mobileFocused: false,
    demandFocused: false,
    companyFocused: false,
    /* overlay*/
    overlayClose: true,
    /* toast */
    toastClose: true,
    /* form field checked */
    formFields: {
      username: {
        maxlen: 30,
        minlen: 2,
        errorMsg: '姓名长度2-30'
      },
      company: {
        maxlen: 30,
        minlen: 0,
        errorMsg: '超过最大长度'
      },
      mobile: {
        maxlen: 11,
        minlen: 11,
        errorMsg: '手机号格式不支持',
        pattern: '^1(\\d){10}$'
      },
      demand: {
        maxlen: 9999999,
        minlen: 10,
        errorMsg: '需求不少于10字'
      }
    }
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    this.setData({ connectstartupid: options.startupid });
  },

  /**
   * 生命周期函数--监听页面初次渲染完成
   */
  onReady: function () {
    wx.hideShareMenu();
    
  },

  /**
   * 生命周期函数--监听页面显示
   */
  onShow: function () {

  },

  /**
   * 生命周期函数--监听页面隐藏
   */
  onHide: function () {

  },

  /**
   * 生命周期函数--监听页面卸载
   */
  onUnload: function () {

  },

  /**
   * 页面相关事件处理函数--监听用户下拉动作
   */
  onPullDownRefresh: function () {

  },

  /**
   * 页面上拉触底事件的处理函数
   */
  onReachBottom: function () {

  },

  /**
   * 用户点击右上角分享
   */
  onShareAppMessage: function () {
    return null;
  },
  focusCtrl: function (e) {
    var name = e.currentTarget.dataset.name;
    this.setData({
      [name + 'Focused']: true,
      [name + 'WithoutError']: true
    })
  },
  blurCtrl: function (e) {
    var name = e.currentTarget.dataset.name;
    var focued = false;
    if (e.detail.value) {
      focued = true;
    }
    this.setData({
      [name + 'Focused']: focued
    })

  },
  formSubmit: function (e) {
    var self = this;
    if (this.validForm(e)) {
      wx.showLoading({
        title: '正在处理您提交的信息...',
      });
      var submitData = e.detail.value;
      submitData.formId = e.detail.formId;
      submitData.to_connect_startupid = this.data.connectstartupid;
      submitData.openid = app.globalData.openid;

      wxreq.fetchData('xx.xx.xx.xx.GetContactxxxxxxx', {
        "data": {
          "sysname": "xx",
          "username": "xx",
          "request_data": {
            'form_data': submitData
          }
        }
      }, {
          successCall: function (res) {
            if (res.data.status == 200) {
              self.setData({ overlayClose: false, toastClose: false });
              
            } else {
              wx.showModal({
                title: '提示',
                content: res.data.message,
              })
            }
            wx.hideLoading();
          }
          
        });

      ;
    } else {
      return false;
    }
  },

  closeForm: function () {
    wx.redirectTo({
      url: '/xx/xx/xx'
    })
  },
  validForm: function (e) {
    var formid = e.detail.formId;
    var errorData = {};
    var errlength = 0;
    for (var index in this.data.formFields) {
      var validation = this.data.formFields[index];
      var keyVal = e.detail.value[index];

      if (
        (keyVal.length < validation.minlen
          || keyVal.length > validation.maxlen) || (
          validation.pattern != undefined && !(new RegExp(validation.pattern).test(keyVal))
        )
      ) {
        errorData[index + 'WithoutError'] = false;
        if (keyVal.replace(' ', '').length == 0) {
          errorData[index + 'ErrorMsg'] = this.data.emptyErrorMsg;

        } else {
          errorData[index + 'ErrorMsg'] = validation.errorMsg;
        }
        errlength++;
      } else {
        errorData[index + 'WithoutError'] = true;
        errorData[index + 'ErrorMsg'] = '';

      }
    }
    // setdata
    this.setData(errorData);

    if (errlength) {
      return false;
    }
    return true;
  }
})
