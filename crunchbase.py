# -*- coding: utf-8 -*-
import os
import sys
import time
import json
import random
import scrapy
import hashlib
import urllib
import threading
import Queue
from django.utils import timezone
from scrapy.http import FormRequest
from twisted.internet import reactor
from Crawler.items import CrunchbaseCompanyItem, CrunchbaseFundingRoundsItem, CrunchbaseCompanyMembersItem
from Crawler.items import CompanyInvestItem, CompanyProductItem
from Crawler.items import CrunchbaseCategoryItem, CrunchbaseHeadquarterItem
from Crawler.items import PersonItem, PersonRelatedCompanyItem, CompanyItem, CrunchbaseCompanyDetailItem
import logging

#根据rank爬取的数据
class CrunchbaseCompanySpider(scrapy.Spider):
    limit = 50
    start_rank = 1
    api_version = 'v4'
    total_num = 100000 # 2-6
    name = "crunchbase_companyspider"
    url_path = 'data/companies/search'
    website = 'http://www.crunchbase.com'
    handle_httpstatus_list = [404,405, 403]
    allowed_domains = ["www.crunchbase.com"]
    website_possible_httpstatus_list = [302, 301, 200, 404, 416, 502, 403]
    headers = {
        "Host":"www.crunchbase.com",
        "Accept-Language":"zh-CN,zh;q=0.8,en;q=0.6",
        "Content-Type":"application/json; charset=utf-8",
        "Connection":"keep-alive",
        "Origin":"https://www.crunchbase.com",
        "X-Distil-Ajax":"sdrwbqyayerexctvtsbrb",
        "Referer":"https://www.crunchbase.com/app/search/companies/aa98c047dde17267a1e0899798d7131bd449975c",
        "X-Requested-With": "XMLHttpRequest"
    }

    def __init__(self, start_rank = 1, total_num = 100000):
        self.start_rank = int(start_rank)
        self.total_num = int(total_num)
        self.body = self.setBody(self.start_rank)
        self.start_urls = [self.website + '/' + self.api_version + '/' + self.url_path]

    def start_requests(self):
        for url in self.start_urls:
            yield scrapy.Request(url=url, method="POST", body=self.body, headers=self.headers, callback=self.parse, dont_filter=True)

    def parse(self, response):
        print "start rank :%s" % self.start_rank
        if response.url.find(self.api_version) != -1:
            try:
                if response.status == 200:
                    # response.body
                    json_response = json.loads(response.body)
                    for item in json_response['entities']:
                        properties = item['properties']
                        category_groups = properties['category_groups']
                        headquarters_identifiers = properties['headquarters_identifiers']
                        identifier = properties['identifier']
                        rank = properties['rank']
                        short_description = properties['short_description']
                        category_groups_str= ''
                        headquarters = ''

                        # initial model
                        company_item = CrunchbaseCompanyItem()
                        for category_group in category_groups:
                            category_item = CrunchbaseCategoryItem()
                            category_item['uuid'] = category_group['uuid']
                            category_item['name'] = category_group['value']
                            category_item['field_name'] = 'category_group'
                            category_item['source'] = 'Crunchbase'
                            category_item['source_url'] = response.url
                            category_groups_str += category_item['uuid'] + ','
                            yield category_item

                        for headquarters_identifier in headquarters_identifiers:
                            headquarters_item = CrunchbaseHeadquarterItem()
                            headquarters_item['uuid'] = headquarters_identifier['uuid']
                            headquarters_item['name'] = headquarters_identifier['value']
                            headquarters_item['field_name'] = 'location'
                            headquarters_item['source'] = 'Crunchbase'
                            headquarters_item['source_url'] = response.url
                            headquarters += headquarters_item['uuid'] + ','
                            yield headquarters_item

                        company_item['short_description'] = short_description
                        company_item['category_groups'] = category_groups_str
                        company_item['headerquarters'] = headquarters
                        company_item['rank'] = rank
                        company_item['uuid'] = identifier['uuid']
                        company_item['name'] = identifier['value']
                        try:
                            company_item['image_id'] = identifier['image_id']
                        except:
                            company_item['image_id'] = ''
                        company_item['field_name'] = 'company'
                        company_item['company_url'] = self.website + '/orgranization/' + company_item['name']
                        company_item['source'] = 'crunchbase'
                        company_item['source_url'] = response.url
                        yield company_item

                    self.start_rank = self.start_rank + self.limit -1
                    if self.start_rank <= self.total_num:
                        self.body = self.setBody(self.start_rank)
                        time.sleep(random.randint(10, 20))
                        yield scrapy.Request(url=response.url, method="POST", body=self.body, headers=self.headers, callback=self.parse, dont_filter=True)
                    else:
                        print response.status , "Done!"
                else:
                    # log
                    self.start_rank = self.start_rank + self.limit -1
                    if self.start_rank <= self.total_num:
                        self.body = self.setBody(self.start_rank)
                        time.sleep(random.randint(10, 20))
                        yield scrapy.Request(url=response.url, method="POST", body=self.body, headers=self.headers, callback=self.parse, dont_filter=True)
                    else:
                        print response.status , "Done!"
            except:
                print response.body
        else:
            print response.body



    def setBody(self, start_rank):
        body = json.dumps({
            "field_ids":["identifier","category_groups","headquarters_identifiers","short_description","rank"],
            "order":[{"field_id":"rank","sort":"asc"}],
            "query":[{"type":"predicate","field_id":"rank","operator_id":"between","values":[start_rank, start_rank + self.limit -1]}],
            "field_aggregators":[],
            "limit": self.limit
        })
        return body

class CompanyDetailSpider(scrapy.Spider):
    name = "crunchbase_company_detailspider"
    nums =5
    endnum = 5
    start = 0
    loadnum = 0
    total = 100
    custom_settings = {
        'DOWNLOADER_MIDDLEWARES': {
            'Crawler.middlewares.HttpProxyMiddleware': 100
        },
    }

    DOWNLOAD_IMG_DIR = 'resource/images'
    IMG_URL = 'https://crunchbase-production-res.cloudinary.com/image/upload/c_pad,h_440,w_440/'
    handle_httpstatus_list = [404,405, 403, 416, 500]
    allowed_domains = ["www.crunchbase.com"]
    website_possible_httpstatus_list = [302, 301, 200]
    headers = {
        "Host":"www.crunchbase.com",
        "Accept-Language":"zh-CN,zh;q=0.8,en;q=0.6",
        "Content-Type":"application/json; charset=utf-8",
        "Connection":"keep-alive",
        # "Origin":"https://www.crunchbase.com",
        # "X-Distil-Ajax":"sdrwbqyayerexctvtsbrb",
        "Referer":"https://www.crunchbase.com/app/search/companies",
        # "X-Requested-With": "XMLHttpRequest"
    }
    def __init__(self, params = '1-2',  *args, **kwargs):
        super(CompanyDetailSpider, self).__init__(*args, **kwargs)
        arr = params.split('-')
        self.start = int(arr[0])
        self.total = int(arr[1])

    def item_to_model(self, item):
        model_class = getattr(item, 'django_model')
        if not model_class:
            raise TypeError("Item is not a `DjangoItem` or is misconfigured")

        return item.instance

    def start_requests(self):
        companyitem = CrunchbaseCompanyItem()
        modle_class = self.item_to_model(companyitem)
        datasetsobj = type(modle_class).objects.all()
        while self.loadnum <= self.total:
            datasets = datasetsobj[self.start:self.endnum]
            for dataset in datasets:
                url = urllib.unquote('https://www.crunchbase.com/organization/' + dataset.company_url.split('/')[-1])
                params_str = dataset.uuid + '|%s' % dataset.id + '|%s' % dataset.name
                yield scrapy.Request(url=urllib.unquote(url), callback=lambda response, params=params_str: self.parse(response, params), headers=self.headers,dont_filter=True)
            time.sleep(random.randint(4, 20))
            self.loadnum = self.endnum
            self.start = self.loadnum + 1
            self.endnum += self.nums

    def parse(self, response, params):
        website_selector = response.css('#info-card-overview-content > div > dl > div.definition-list.container > dd:nth-child(10) > a::attr("href")')
        facebook_link_selector = response.css('#info-card-overview-content > div > dl > div.definition-list.container > dd.social-links > a.facebook::attr("href")')
        twitter_link_selector = response.css('#info-card-overview-content > div > dl > div.definition-list.container > dd.social-links > a.twitter::attr("href")')
        linkedin_link_selector = response.css('#info-card-overview-content > div > dl > div.definition-list.container > dd.social-links > a.linkedin::attr("href")')
        founders_selector = response.css('#info-card-overview-content > div > dl > div.definition-list.container > dd:nth-child(6) > a')
        ipo_detail_selector = response.css('#info-card-overview-content > div > dl > div.overview-stats > dd:nth-child(4) > a:nth-child(1)::attr("href")')
        introduction_selector = response.css('#description > span > div > p')
        investor_selector = response.css('#main-content > div.columns.large-14.small-14.container > div.columns.small-10.large-10.first-column > div.timeline.columns.large-8.small-8.container.pull-right > div.base.info-tab.investors > div.card-content.box.container.card-slim > table')
        ipo_date_selector = response.xpath('//*[@id="info-card-overview-content"]/div/dl/div[1]/dd[2]/text()')
        slider_images_selector = response.css('#main-content > div.columns.large-14.small-14.container > div.columns.small-10.large-10.first-column > div.timeline.columns.large-8.small-8.container.pull-right > div.base.info-tab.images > div.card-content.box.container.card-slim > ul > li')
        # 以下四个都有可能不存在
        # founded date 第一个
        definitions_selector = response.css('#main-content > div.columns.large-14.small-14.container > div.columns.small-10.large-10.first-column > div.timeline.columns.large-8.small-8.container.pull-right > div.base.info-tab.description > div.card-content.box.container.card-slim > div.details.definition-list')
        definition_dd_value_selector = definitions_selector.css('dd::text').extract()
        definition_dt_selector = definitions_selector.css('dt::text').extract()

        # funding rounds
        funding_table_tr_selector = response.css('#main-content > div.columns.large-14.small-14.container > div.columns.small-10.large-10.first-column > div.timeline.columns.large-8.small-8.container.pull-right > div.base.info-tab.funding_rounds > div.card-content.box.container.card-slim > table > tr')
        if funding_table_tr_selector.extract_first() is not None:
            for tr_selector in funding_table_tr_selector:
                item = CrunchbaseFundingRoundsItem()
                item['fund_date'] = tr_selector.xpath('td[1]/text()').extract_first()
                item['fund_amount'] = tr_selector.xpath('td[2]/text()').extract_first()
                item['fund_round'] = tr_selector.xpath('td[2]/a/text()').extract_first()
                item['valuation'] = tr_selector.xpath('td[3]/text()').extract_first()
                item['source'] = 'crunchbase'
                item['source_url'] = response.url
                table_row_td = tr_selector.css('td.table_row').extract_first()
                if table_row_td is None:
                    table_cell_divs = tr_selector.css('td.table_row > div.table_cell')
                    for table_cell_div in table_cell_divs:
                        item['lead_investors'] += table_cell_div.xpath('a/text()').extract_first() + ","

                else:
                    item['lead_investors'] = tr_selector.xpath('td[4]/a/text()').extract_first()
                yield item

        members_selector = response.css('#main-content > div.columns.large-14.small-14.container > div.columns.small-10.large-10.first-column > div.timeline.columns.large-8.small-8.container.pull-right > div.base.info-tab.people > div.card-content.box.container.card-slim > ul > li')

        for member_selector in members_selector:
            if  member_selector.xpath('span[1]/a/@href').extract_first() is not None:
                person_source_url = "https://www.crunchbase.com" + member_selector.xpath('span[1]/a/@href').extract_first()
                yield scrapy.Request(url=person_source_url,
                                     callback=self.parse_member_item,
                                     headers=self.headers, dont_filter=True)
        # contact 第三个
        # scale 最后一个
        # alias 第二个
        founded_date = ''
        employees_scale = ''
        alias = ''
        contact = ''
        for i in range(len(definition_dt_selector)):
            cur_selector = definition_dt_selector[i].lower()
            cur_selector_value = definition_dd_value_selector[i].strip()
            if cur_selector.find('founded') != -1:
                founded_date = cur_selector_value
            elif cur_selector.find('employees') != -1:
                employees_scale = cur_selector_value.replace(' ', "").replace('|', "")
            elif cur_selector.find('alias') != -1:
                alias = cur_selector_value
            elif cur_selector.find('contact') != -1:
                contact = cur_selector_value
        detail_item = CrunchbaseCompanyDetailItem()
        try:
            ipo_date = ipo_date_selector.extract_first().strip().replace("on", "").replace(" ", "").replace('/', "")
            ipo_platform = response.xpath('//*[@id="info-card-overview-content"]/div/dl/div[1]/dd[2]/a[2]/text()')
            detail_item['ipoinfo'] = ipo_date + '|' +ipo_platform
        except:
            detail_item['ipoinfo'] = ''

        detail_item['slider_images'] = ''
        if slider_images_selector.extract_first() is not None:
            for li_img_selector in slider_images_selector:
                img_url = li_img_selector.css('a::attr("href")').extract_first()
                detail_item['slider_images'] += img_url + ','

        split_arr = params.split('|')
        detail_item['scale'] = employees_scale
        detail_item['alias'] = alias
        detail_item['contact'] = contact
        detail_item['founded_date'] = founded_date

        detail_item['news_url'] = response.url + '/press'
        detail_item['products_url'] = response.url + '/products'
        detail_item['acquisitions_url'] = response.url + '/acquisitions'
        detail_item['employees_url'] = response.url + '/people'
        detail_item['investors_url'] = response.url + '/investors'

        detail_item['introduction'] = ''
        for introduction_p_selector in introduction_selector:
            detail_item['introduction'] += introduction_p_selector.css('::text').extract_first()
        detail_item['company_id'] = split_arr[1]
        detail_item['name'] = split_arr[-1]
        detail_item['uuid'] = split_arr[0]
        detail_item['website'] = '' if website_selector.extract_first() is None else website_selector.extract_first()
        detail_item['facebook_url'] = '' if facebook_link_selector.extract_first() is None else facebook_link_selector.extract_first()
        detail_item['twitter_url'] = '' if twitter_link_selector.extract_first() is None else twitter_link_selector.extract_first()
        detail_item['linkedin_url'] = '' if linkedin_link_selector.extract_first() is None else linkedin_link_selector.extract_first()
        detail_item['source'] = 'crunchbase'
        detail_item['source_url'] = response.url
        yield detail_item

    def parse_member_item(self, response):
        item = CrunchbaseCompanyMembersItem()
        item['realname'] = response.xpath('//*[@id="profile_header_heading"]/a/text()').extract_first()
        item['name'] = self._encrypt_with_md5(item['realname'])
        item['uuid'] = response.xpath('//*[@id="info-card-overview-content"]/div/dl/div/dd[1]/a/@data-uuid').extract_first()
        item['title'] = response.xpath('//*[@id="info-card-overview-content"]/div/dl/div/dd[1]/text()').extract_first()
        item['avatar_url'] = response.xpath('//*[@id="left-rail"]/div/div[1]/div/img/@src').extract_first()
        item['person_detail'] = response.xpath('//*[@id="description"]/span/div/p//text()').extract_first()
        item['source'] = 'crunchbase'
        item['source_url'] = reponse.url

        item['gender'] = ''
        item['born'] = ''
        item['location'] = ''
        item['facebook_url'] = ''
        item['linkedin_url'] = ''
        item['twitter_url'] = ''

        dt_selectors= response.xpath('//*[@id="info-card-overview-content"]/div/dl/dt')
        dd_selectors = response.xpath('//*[@id="info-card-overview-content"]/div/dl/dd')
        for index in range(len(dt_selectors)):
            current_selector_text = dt_selectors[index].xpath('text()').extract_first()
            if current_selector_text is not None and current_selector_text.find('Gender') != -1:
                item['gender'] = dd_selectors[index].xpath('text()').extract_first()
            elif current_selector_text is not None and current_selector_text.find('Born') != -1:
                item['born'] = dd_selectors[index].xpath('text()').extract_first()
            elif current_selector_text is not None and current_selector_text.find('Location') != -1:
                item['location'] = dd_selectors[index].xpath('text()').extract_first()
                pass
            elif current_selector_text is not None and current_selector_text.find('Social') != -1:
                social_link_selector = dd_selectors[index].xpath('a')
                for social_link in social_link_selector:
                    data_icons = social_link.xpath('@data-icons')
                    if data_icons == 'facebook':
                        item['facebook_url'] = social_link.xpath('@href')
                        pass
                    elif data_icons == 'twitter':
                        item['twitter_url'] = social_link.xpath('@href')
                        pass
                    elif data_icons == 'linkedin':
                        item['linkedin_url'] = social_link.xpath('@href')
                        pass
                    else:
                        pass
            else:
                pass
        yield item

    def _encrypt_with_md5(self, str):
        m2 = hashlib.md5()
        m2.update(str)
        str_md5 = m2.hexdigest()
        return str_md5
