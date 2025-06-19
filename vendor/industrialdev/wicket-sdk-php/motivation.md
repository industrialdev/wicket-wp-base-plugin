# Dropbox

> https://www.dropbox.com/developers-v1/core/docs

```php
list($accessToken, $host) = \Dropbox\AuthInfo::loadFromJsonFile($nonOptionArgs[0]);
$client = new \Dropbox\Client($accessToken, "examples-$exampleName", $locale, $host);
$metadata = $client->uploadFile($dropboxPath, \Dropbox\WriteMode::add(), $fp, $size);
```

# Twitter

> https://dev.twitter.com/rest/reference/get/statuses/home_timeline

```php
$client = new \GuzzleHttp\Client(['base_url' => 'https://api.twitter.com/1.1/']);
$oauth = new Oauth1([ 'consumer_key' => '...', 'consumer_secret' => '...', 'token' => '...', 'token_secret' => '...', ]);
$client->getEmitter()->attach($oauth);
$res = $client->get('statuses/home_timeline.json', ['auth' => 'oauth'])->json();
```

# MailChimp

```php
use \DrewM\MailChimp\MailChimp;
$MailChimp = new MailChimp('abc123abc123abc123abc123abc123-us1');
```

Then, list all the mailing lists (with a get on the lists method)

```php
$result = $MailChimp->get('lists');
print_r($result);
```

Subscribe someone to a list (with a post to the list/{listID}/members method):

```php
$list_id = 'b1234346';
$result = $MailChimp->post("lists/$list_id/members", [
                'email_address' => 'davy@example.com',
                'status'        => 'subscribed',
            ]);
print_r($result);
```

Update a list member with more information (using patch to update):

```php
$list_id = 'b1234346';
$subscriber_hash = $MailChimp->subscriberHash('davy@example.com');
$result = $MailChimp->patch("lists/$list_id/members/$subscriber_hash", [
                'merge_fields' => ['FNAME'=>'Davy', 'LNAME'=>'Jones'],
                'interests'    => ['2s3a384h' => true],
            ]);
print_r($result);
```

Remove a list member using the delete method:

```php
$list_id = 'b1234346';
$subscriber_hash = $MailChimp->subscriberHash('davy@example.com');
$MailChimp->delete("lists/$list_id/members/$subscriber_hash");
```

### CREATE PERSON w/ RELATIONS (person.json)

```
{
	"data": {
		"attributes": {
			"given_name": "given",
			"family_name": "family"
		},
		"relationships": {
			"emails": {
				"data": [
					{ "type": "emails", "attributes": { "address": "name@ind.ninja", "primary": true } }
				]
			}
		}
	}
}
*/

/* CREATE PERSON RESPONSE
scott@kudu:~/wicket/wicket-sdk-php$ http POST :3000/organizations/58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a/people/ < person.json

HTTP/1.1 200 OK
Cache-Control: max-age=0, private, must-revalidate
Connection: keep-alive
Content-Length: 1661
Content-Type: application/json; charset=utf-8
ETag: W/"f5e9e08a4544988b69c2a643b6e0032d"
Server: thin 1.5.1 codename Straight Razor
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-Request-Id: 670d8367-6c91-4907-91aa-af8db9249bfb
X-Runtime: 0.264716
X-XSS-Protection: 1; mode=block

{
    "data": {
        "type": "people",
        "id": "d50a18cf-67f7-4d55-bf65-36504c46f53d",
        "attributes": {
            "additional_name": null,
            "alternate_name": "given family",
            "birth_date": null,
            "created_at": "2016-04-12T17:47:37.114Z",
            "data": {},
            "deleted_at": null,
            "family_name": "family",
            "gender": null,
            "given_name": "given",
            "honorific_prefix": null,
            "honorific_suffix": null,
            "job_title": null,
            "language": null,
            "slug": "given-family-f440c6d0-8ee9-485d-859b-27ec5719fdc7",
            "updated_at": "2016-04-12T17:47:37.114Z",
            "user": null,
            "uuid": "d50a18cf-67f7-4d55-bf65-36504c46f53d"
        },
        "relationships": {
            "addresses": {
                "data": []
            },
            "emails": {
                "data": [
                    {
                        "id": "050902fd-d99e-4bf6-a66f-a853acf0b804",
                        "type": "emails"
                    }
                ]
            },
            "identities": {
                "data": []
            },
            "orders": {
                "data": []
            },
            "organizations": {
                "data": [
                    {
                        "id": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
                        "type": "organizations"
                    }
                ],
                "meta": {
                    "pivot": [
                        {
                            "id": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
                            "type": null
                        }
                    ]
                }
            },
            "phones": {
                "data": []
            }
        }
    },
    "included": [
        {
            "attributes": {
                "alternate_name": "PACA",
                "ancestry": null,
                "created_at": "2016-04-11T18:15:36.257Z",
                "deleted_at": null,
                "description": "Physiotherapy Alberta College and Association",
                "duns": null,
                "inheritable_from_parent": [
                    "memberships"
                ],
                "inherits_from_parent": {},
                "legal_name": "Physiotherapy Alberta College and Association",
                "people_count": 1,
                "slug": "physiotherapy-alberta-college-and-association",
                "type": "licensing",
                "updated_at": "2016-04-11T18:15:36.257Z",
                "uuid": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a"
            },
            "id": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
            "meta": {
                "ancestry_depth": 0
            },
            "relationships": {
                "addresses": {
                    "data": []
                },
                "emails": {
                    "data": []
                },
                "insurance_options": {
                    "data": []
                },
                "memberships": {
                    "data": []
                },
                "payment_methods": {
                    "data": []
                },
                "phones": {
                    "data": []
                }
            },
            "type": "organizations"
        }
    ]
}

$ http :3000/people/d50a18cf-67f7-4d55-bf65-36504c46f53d
{
  "data": {
    "id": "d50a18cf-67f7-4d55-bf65-36504c46f53d",
    "type": "people",
    "attributes": {
      "uuid": "d50a18cf-67f7-4d55-bf65-36504c46f53d",
      "given_name": "given",
      "family_name": "family",
      "additional_name": null,
      "alternate_name": "given family",
      "slug": "given-family-f440c6d0-8ee9-485d-859b-27ec5719fdc7",
      "gender": null,
      "honorific_prefix": null,
      "honorific_suffix": null,
      "job_title": null,
      "birth_date": null,
      "created_at": "2016-04-12T17:47:37.114Z",
      "updated_at": "2016-04-12T17:47:37.114Z",
      "deleted_at": null,
      "data": {},
      "language": null,
      "current_membership_summary": {
        "root_membership_name": null,
        "root_membership_number": null,
        "branch_name": null,
        "division_names": []
      },
      "user": null
    },
    "relationships": {
      "organizations": {
        "data": [
          {
            "id": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
            "type": "organizations"
          }
        ],
        "meta": {
          "pivot": [
            {
              "id": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
              "type": null
            }
          ]
        }
      },
      "phones": {
        "data": []
      },
      "emails": {
        "data": [
          {
            "id": "050902fd-d99e-4bf6-a66f-a853acf0b804",
            "type": "emails"
          }
        ]
      },
      "addresses": {
        "data": []
      },
      "orders": {
        "data": []
      },
      "identities": {
        "data": []
      }
    }
  },
  "included": [
    {
      "id": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
      "type": "organizations",
      "attributes": {
        "uuid": "58dfc86c-7ce9-4696-bb8c-a4e6b5289e4a",
        "alternate_name": "PACA",
        "legal_name": "Physiotherapy Alberta College and Association",
        "description": "Physiotherapy Alberta College and Association",
        "slug": "physiotherapy-alberta-college-and-association",
        "ancestry": null,
        "duns": null,
        "people_count": 1,
        "created_at": "2016-04-11T18:15:36.257Z",
        "updated_at": "2016-04-11T18:15:36.257Z",
        "deleted_at": null,
        "type": "licensing",
        "inheritable_from_parent": [
          "memberships"
        ],
        "inherits_from_parent": {}
      },
      "relationships": {
        "phones": {
          "data": []
        },
        "emails": {
          "data": []
        },
        "addresses": {
          "data": []
        },
        "memberships": {
          "data": []
        },
        "insurance_options": {
          "data": []
        },
        "payment_methods": {
          "data": []
        }
      },
      "meta": {
        "ancestry_depth": 0
      }
    },
    {
      "id": "050902fd-d99e-4bf6-a66f-a853acf0b804",
      "type": "emails",
      "attributes": {
        "uuid": "050902fd-d99e-4bf6-a66f-a853acf0b804",
        "localpart": "created",
        "domain": "ind.ninja",
        "type": null,
        "created_at": "2016-04-12T17:47:37.132Z",
        "updated_at": "2016-04-12T17:47:37.132Z",
        "deleted_at": null,
        "address": "created@ind.ninja",
        "primary": true
      },
      "relationships": {
        "emailable": {
          "data": {
            "id": "d50a18cf-67f7-4d55-bf65-36504c46f53d",
            "type": "people"
          }
        },
        "organization": {
          "data": null
        }
      }
    }
  ]
}
```
