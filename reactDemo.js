
class ISlider extends Component {

    constructor(props) {
        super(props);
        this.imgArray = props.img;
    }
    createImgslider(img, i) {
        return (<Element
            prefixCls="banner-user-elem"
            key={i}
        >
            <BgElement
                key="bg"
                className="bg"
                style={{
                    background: `url(${img}) no-repeat`
                }}
            />
        </Element>);
    }

    render(){
        let self = this;
        
        let imgLists=[];

        this.imgArray.forEach(function(v,i){
            imgLists.push(self.createImgslider(v,i))
        });
        return (

            <BannerAnim prefixCls="banner-user" type="custom">
                {imgLists}
            </BannerAnim>);
    }
}
