// components/menubar/menubar.js
Component({
  /**
   * 组件的属性列表
   */
  properties: {
    title: {
      type: String,
      value: ""
    },
    normal: {
      type: String,
      value: "0"
    },
    showback: {
      type: String,
      value: "0"
    },
    backpage: {
      type: String,
      value: ""
    },
    index: {
      type: String,
      value: ""
    }
  },
  ready: function(options) {
  },
  options: {
    multipleSlots: true // 在组件定义时的选项中启用多slot支持
  },

  /**
   * 组件的初始数据
   */
  data: {

  },

  /**
   * 组件的方法列表
   */
  methods: {
    backHome: function(e) {
      if(e.currentTarget.dataset.index) {
        wx.navigateBack();
        return false;
      }
      // 最多2级页面
      if (e.currentTarget.dataset.backpage) {
        //原来的
        // wx.redirectTo({
        //   url: e.currentTarget.dataset.backpage,
        // });
        //现在的
        wx.navigateBack();
      } else {
        wx.reLaunch({
          url: '/pages/index/index',
        })
      }
    },
    redirectHome: function() {
      wx.reLaunch({
        url: '/pages/index/index',
      })
    }
  }
})
