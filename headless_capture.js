// 读取本地
const capture_local = function(req) {
    var filename = req.filename;
    const puppeteer = require('puppeteer');
    const devices = require('puppeteer/DeviceDescriptors');
    (async () => {
        var qs = require('qs');
        const browser = await puppeteer.launch({args: ['--no-sandbox', '--disable-setuid-sandbox']});
        const page = await browser.newPage();
        // const iPhone = devices['iPhone 6'];
        // await page.emulate(iPhone);
        var width,height,viewport;

        await  page.goto('file:///usr/local/var/www/headless/screenshot/index.html');
        var startup = eval('(' + req.screendata + ')');

        await page.evaluate((startup) => {loadContent(startup)}, startup);
        await page.waitFor(1500)
        // Get the "viewport" of the page, as reported by the page.
        const dimensions = await page.evaluate(() => {
            return {
                width: document.documentElement.clientWidth,
                height: document.documentElement.clientHeight,
                deviceScaleFactor: window.devicePixelRatio
            };
        });
        viewport={
            width:dimensions.width,
            height:dimensions.height
        };
        await page.setViewport(viewport);
        path = '/capture/' + filename;
        if (filename != undefined && filename.split('.')[1] == 'pdf'){
            await page.pdf({path: path, format: 'A4'});
        } else {
            await page.screenshot({path:path,fullPage: true});
        }
        await browser.close();
    })();
}
