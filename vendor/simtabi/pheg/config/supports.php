<?php declare(strict_types=1);

return [

    /**
     * #######################################################################
     *
     * DEFAULT APP STATUSES
     *
     * #######################################################################
     *
     */
    'environments' => [
        'demonstration' => 'Demonstration',
        'development'   => 'Development',
        'maintenance'   => 'Maintenance',
        'debugging'     => 'Debugging',
        'beta'          => 'Beta',
        'live'          => 'Live',
    ],

    /**
     * #######################################################################
     *
     * DEFAULT USER GROUPS
     *
     * #######################################################################
     *
     */
    'user_groups' => [
        'root_admin'    => 'Root Admin',
        'super_admin'   => 'Super Admin',
        'manager'       => 'Manager',
        'admin'         => 'Admin',
        'moderator'     => 'Moderator',
        'reviewer'      => 'Reviewer',
        'blogger'       => 'Blogger',
        'writer'        => 'Writer',
        'editor'        => 'Editor',
        'developer'     => 'Developer',
        'beta_tester'   => 'Beta Tester',
        'creative'      => 'Creative',
        'vendor'        => 'Vendor',
        'client'        => 'OpenAiClient',
        'regular'       => 'Regular',
        'guest'         => 'Guest',
    ],

    /**
     * #######################################################################
     *
     * DEFAULT ACCESS GROUPS
     *
     * #######################################################################
     *
     */
    'access_groups' => [
        'registered' => 'Registered',
        'public'     => 'Public',
        'guest'      => 'Guest',
        'admin'      => 'Admin',
        'none'       => 'None',
        'all'        => 'All',
    ],

    /**
     * #######################################################################
     *
     * DEFAULT SECURITY STATUSES
     *
     * #######################################################################
     *
     */
    'security_statuses'         => [
        'confirmed'          => 'Confirmed',
        'blocked'            => 'Blocked',
        'flagged'            => 'Flagged',
        'suspended'          => 'Suspended',
        'banned'             => 'Banned',
        'blacklisted'        => 'Blacklisted',
        'temporary'          => 'Temporary',
        'pending'            => 'Pending',
    ],

    /**
     * #######################################################################
     *
     * DEFAULT AGE LIMITS
     *
     * #######################################################################
     *
     */
    'age_limits' => [
        18 =>  '18 Years',
        21 =>  '21 Years',
    ],

    /**
     * #######################################################################
     *
     * DEFAULT TRIGGER FREQUENCIES
     *
     * #######################################################################
     *
     */
    'trigger_frequencies'                  => [
        'random'  => 'Random',
        'hourly'  => 'Hourly',
        'daily'   => 'Daily',
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
    ],

    /**
     * #######################################################################
     *
     * DEFAULT TIME & CLOCK SETTINGS
     *
     * #######################################################################
     *
     */
    'time_options' => [
        'clock' => [
            12 => '12 Hour',
            24 => '24 Hour',
        ],
        'unit' => [
            'seconds' => 'Seconds',
            'minutes' => 'Minutes',
            'hours'   => 'Hours',
        ],
    ],

    /**
     * #######################################################################
     *
     * DEFAULT CALENDAR SETTINGS
     *
     * #######################################################################
     *
     */
    'calendar_options' => [
        'week' => [
            'sunday'    => 'Sunday',
            'monday'    => 'Monday',
            'tuesday'   => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday'  => 'Thursday',
            'friday'    => 'Friday',
            'saturday'  => 'Saturday',
        ],
        'months' => [
            'january'   => 'January',
            'february'  => 'February',
            'march'     => 'March',
            'april'     => 'April',
            'may'       => 'May',
            'june'      => 'June',
            'july'      => 'July',
            'august'    => 'August',
            'september' => 'September',
            'october'   => 'October',
            'november'  => 'November',
            'december'  => 'December',
        ],
    ],

    /**
     * #######################################################################
     *
     * DEFAULT DATETIME FORMAT SETTINGS
     *
     * #######################################################################
     *
     */ //
    'datetime_formats' => [
        'datetime' => [
            'long'  => [
                'A_d_B_Y_H_M' => [
                    'human' => 'Monday 20 March 2014 07:00',
                    'blog'  => 'A d B, Y H:M',
                    'sql'   => '%A %d %B, %Y %H:%M',
                ],
                'B_d_Y_I_M_p' => [
                    'human' => 'March 20, 2014 10:00 am',
                    'blog'  => 'B d, Y I:M p',
                    'sql'   => '%B %d, %Y %I:%M %p',
                ],
                'd_B_Y_H_M' => [
                    'human' => '20 March 2014 05:00',
                    'blog'  => 'd B Y H:M',
                    'sql'   => '%d %B %Y %H:%M',
                ],
                'A_d_B_Y' => [
                    'human' => 'Tuesday 20 March, 2014',
                    'blog'  => 'A d B, Y',
                    'sql'   => '%A %d %B, %Y',
                ],
                'B_d_Y' => [
                    'human' => 'March 20, 2014',
                    'blog'  => 'B d, Y',
                    'sql'   => '%B %d, %Y',
                ],
                'a_d_B' => [
                    'human' => 'Tue. 20, March',
                    'blog'  => 'a d, B',
                    'sql'   => '%a %d, %B',
                ],
                'Y_m_d_H_i' => [
                    'human' => 'Tue. 20, March',
                    'blog'  => 'Y-m-d H:i',
                    'sql'   => '%Y-%m-%d %H:%i:%s',
                ],
            ],
            'short' => [
                'm_d_Y' => [
                    'human' => '03-20-2014 (MM-DD-YYYY)',
                    'blog'  => 'm-d-Y',
                    'sql'   => '%m-%d-%Y',
                ],
                'e_m_Y' => [
                    'human' => '20-03-2014 (D-MM-YYYY)',
                    'blog'  => 'e-m-Y',
                    'sql'   => '%e-%m-%Y',
                ],
                'm_e_y' => [
                    'human' => '20-20-09 (MM-D-YY)',
                    'blog'  => 'm-e-y',
                    'sql'   => '%m-%e-%y',
                ],
                'e_m_y' => [
                    'human' => '20-03-09 (D-MM-YY)',
                    'blog'  => 'e-m-y',
                    'sql'   => '%e-%m-%y',
                ],
                'b_d_Y' => [
                    'human' => 'Mar 20 2014',
                    'blog'  => 'b d Y',
                    'sql'   => '%b %d %Y',
                ],
            ],
        ],
        'date'     => [
            'long'  => [
                'A_d_B_Y_H_M' => [
                    'human' => 'Monday 20 March 2014 07:00',
                    'blog'  => 'A d B, Y H:M',
                    'sql'   => '%A %d %B, %Y %H:%M',
                ],
                'B_d_Y_I_M_p' => [
                    'human' => 'March 20, 2014 10:00 am',
                    'blog'  => 'B d, Y I:M p',
                    'sql'   => '%B %d, %Y %I:%M %p',
                ],
                'd_B_Y_H_M' => [
                    'human' => '20 March 2014 05:00',
                    'blog'  => 'd B Y H:M',
                    'sql'   => '%d %B %Y %H:%M',
                ],
                'A_d_B_Y' => [
                    'human' => 'Tuesday 20 March, 2014',
                    'blog'  => 'A d B, Y',
                    'sql'   => '%A %d %B, %Y',
                ],
                'B_d_Y' => [
                    'human' => 'March 20, 2014',
                    'blog'  => 'B d, Y',
                    'sql'   => '%B %d, %Y',
                ],
                'a_d_B' => [
                    'human' => 'Tue. 20, March',
                    'blog'  => 'a d, B',
                    'sql'   => '%a %d, %B',
                ],
            ],
            'short' => [
                'm_d_Y' => [
                    'human' => '03-20-2014 (MM-DD-YYYY)',
                    'blog'  => 'm-d-Y',
                    'sql'   => '%m-%d-%Y',
                ],
                'e_m_Y' => [
                    'human' => '20-03-2014 (D-MM-YYYY)',
                    'blog'  => 'e-m-Y',
                    'sql'   => '%e-%m-%Y',
                ],
                'm_e_y' => [
                    'human' => '20-20-09 (MM-D-YY)',
                    'blog'  => 'm-e-y',
                    'sql'   => '%m-%e-%y',
                ],
                'e_m_y' => [
                    'human' => '20-03-09 (D-MM-YY)',
                    'blog'  => 'e-m-y',
                    'sql'   => '%e-%m-%y',
                ],
                'b_d_Y' => [
                    'human' => 'Mar 20 2014',
                    'blog'  => 'b d Y',
                    'sql'   => '%b %d %Y',
                ],
            ],
        ],
        'time'     => [
            'long'  => [
                'G_i_s' => [
                    'human' => '23:03:09 (G:is)',
                    'blog'  => 'G:i s',
                    'sql'   => '%G:%i%s',
                ],
                'H_i_s_P' => [
                    'human' => '23:03:09 -04:00 (H:i:s P) + Timezone Offset',
                    'blog'  => 'H:i:s P',
                    'sql'   => '%H:%i:%s %P',
                ],
                'G_i_s_u' => [
                    'human' => '23:03:09.705055 (G:i:s.u) + Microseconds',
                    'blog'  => 'G:i:s.u',
                    'sql'   => '%G:%i%s.%u',
                ],
                'U' => [
                    'human' => '1615950189 (U) Unix Epoch',
                    'blog'  => 'U',
                    'sql'   => '%U',
                ],
            ],
            'short' => [
                'g_i_a' => [
                    'human' => '11:03 pm (g:i a)',
                    'blog'  => 'g:i a',
                    'sql'   => '%g:%i %a',
                ],
            ],
        ],
        'js'       => [
            'datetime' => [
                'YYYY_MM_DD_HH_mm' => 'YYYY-MM-DD HH:mm',
                'yyyy_mm_dd_H_i_s' => 'yyyy-mm-dd H:i:s',
            ],
            'date'     => [
                'YYYY_MM_DD' => 'YYYY-MM-DD',
                'yyyy_mm_dd' => 'yyyy-mm-dd',
            ],
        ],
    ],


    /**
     * #######################################################################
     *
     * DEFAULT LANGUAGE SETTINGS
     *
     * #######################################################################
     *
     */
    'language_options' => [
        'default' => 'en_US',
        'direction' => [
            'ltr' => 'Left to Right',
            'rtl' => 'Right to Left'
        ],
    ],

    /**
     * #######################################################################
     *
     * DEFAULT GENERIC SETTINGS
     *
     * #######################################################################
     *
     */
    'link_target_options' => [
        '_blank'  => 'Blank',
        '_parent' => 'Parent',
        '_self'   => 'Self',
        '_top'    => 'Top',
    ],

    'priority_types' => [
        'blocker'     => 'Blocker',
        'critical'    => 'Critical',
        'medium'      => 'Medium',
        'normal'      => 'Normal',
        'urgent'      => 'Urgent',
        'high'        => 'High',
        'low'         => 'Low',
    ],

    'content_statuses' => [
        'retracted' => 'Retracted',
        'published' => 'Published',
        'archived'  => 'Archived',
        'review'    => 'Review',
        'draft'     => 'Draft',
    ],

    'progress_status_types' => [
        'long_overdue' => 'Long overdue',
        'completed'    => 'Completed',
        'starting'     => 'Starting',
        'started'      => 'Started',
        'paused'       => 'Paused',
        'stalled'      => 'Stalled',
        'overdue'      => 'Overdue',
        'almost'       => 'Almost',
        'wip'          => 'Work In Progress',
    ],

    // Common industry media aspect ratios
    // http:// www.rtings.com/info/what-is-the-aspect-ratio-4-3-16-9-21-9
    'aspect_ratios' => [
        '4:3'  => '4:3  - Standard Definition',
        '16:9' => '16:9 - High Definition',
        '21:9' => '21:9 - Most Movies',
    ],

    'avatar_types' => [
        'uploaded' => 'Uploaded',
        'gravatar' => 'Gravatar',
    ],

    'upload_methods' => [
        'file' => 'File',
        'link' => 'Link',
    ],

    'genders' => [
        'male'   => 'Male',
        'female' => 'Female',
        'other'  => 'Other',
    ],

    'availability_types' => [
        'not_available' => 'Not Available',
        'available'     => 'Available',
        'maybe'         => 'Maybe',
    ],

    'employment_status_types'  => [
        'freelancer' => 'Freelancer',
        'employed'   => 'Employed',
        'student'    => 'Student',
        'other'      => 'Other',
    ],

    'socialmedia_providers'             => [
        'twitter'    => 'https://wwww.twitter.com/',
        'facebook'   => 'https://wwww.facebook.com/',
        'linkedin'   => 'https://wwww.linkedin.com/',
        'github'     => 'https://wwww.github.com/',
        'bitbucket'  => 'https://wwww.bitbucket.com/',
        'behance'    => 'https://wwww.behance.com/',
        'dribbble'   => 'https://wwww.dribbble.com/',
        'vimeo'      => 'https://wwww.vimeo.com/',
        'medium'     => 'https://wwww.medium.com/',
        'dunked'     => 'https://wwww.dunked.com/',
        'envato'     => 'https://wwww.envato.com/',
        'youtube'    => 'https://wwww.youtube.com/',
        'instagram'  => 'https://wwww.instagram.com/',
        'add_custom' => 'Add Custom',
    ],

    'salutations' => [
        "mr"                   => "Mr",
        "mrs"                  => "Mrs",
        "miss"                 => "Miss",
        "ms"                   => "Ms",
        "dr"                   => "Dr",
        "admiral"              => "Admiral",
        "air_comm"             => "Air Comm",
        "ambassador"           => "Ambassador",
        "baron"                => "Baron",
        "baroness"             => "Baroness",
        "brig_mrs"             => "Brig & Mrs",
        "brig_gen"             => "Brig Gen",
        "brigadier"            => "Brigadier",
        "canon"                => "Canon",
        "capt"                 => "Capt",
        "chief"                => "Chief",
        "cllr"                 => "Cllr",
        "col"                  => "Col",
        "commander"            => "Commander",
        "commander_mrs"        => "Commander & Mrs",
        "consul"               => "Consul",
        "consul_general"       => "Consul General",
        "count"                => "Count",
        "countess"             => "Countess",
        "countess_of"          => "Countess of",
        "cpl"                  => "Cpl",
        "dame"                 => "Dame",
        "deputy"               => "Deputy",
        "dr_&_mrs"             => "Dr & Mrs",
        "drs"                  => "Drs",
        "duchess"              => "Duchess",
        "duke"                 => "Duke",
        "earl"                 => "Earl",
        'father'               => 'Father',
        'pastor'               => 'Pastor',
        'brother'              => 'Brother',
        'sister'               => 'Sister',
        'elder'                => 'Elder',
        "general"              => "General",
        "grafin"               => "Gräfin",
        "he"                   => "HE",
        "hma"                  => "HMA",
        "her_grace"            => "Her Grace",
        "his_excellency"       => "His Excellency",
        "ing"                  => "Ing",
        "judge"                => "Judge",
        "justice"              => "Justice",
        "lady"                 => "Lady",
        "lic"                  => "Lic",
        "llc"                  => "Llc",
        "lord"                 => "Lord",
        "lord_lady"            => "Lord & Lady",
        "lt"                   => "Lt",
        "lt_col"               => "Lt Col",
        "lt_cpl"               => "Lt Cpl",
        'lt_cmdr'              => 'Lt.-Cmdr',
        'cmdr'                 => 'Cmdr',
        'flt_lt'               => 'Flt. Lt',
        'brgdr'                => 'Brgdr',
        'wng_cmdr'             => 'Wng. Cmdr',
        'group_capt'           => 'Group Capt',
        'rt_hon_lord'          => 'Rt. Hon. Lord',
        'revd_father'          => 'Revd. Father',
        'revd_canon'           => 'Revd Canon',
        'eng'                  => 'Engineer',
        'maj_gen'              => 'Maj.-Gen',
        'air_cdre'             => 'Air Cdre',
        'rear_dmrl'            => 'Rear Admrl',
        "m"                    => "M",
        "madam"                => "Madam",
        "madame"               => "Madame",
        "major"                => "Major",
        "major_general"        => "Major General",
        "marchioness"          => "Marchioness",
        "marquis"              => "Marquis",
        "minister"             => "Minister",
        "mme"                  => "Mme",
        "mr_dr"                => "Mr & Dr",
        "mr_mrs"               => "Mr & Mrs",
        "mr_ms"                => "Mr & Ms",
        "prince"               => "Prince",
        "princess"             => "Princess",
        "professor"            => "Professor",
        "prof"                 => "Prof",
        "prof_mrs"             => "Prof & Mrs",
        "prof_rev"             => "Prof & Rev",
        "prof_dame"            => "Prof Dame",
        "prof_dr"              => "Prof Dr",
        "pvt"                  => "Pvt",
        "rabbi"                => "Rabbi",
        "rear_admiral"         => "Rear Admiral",
        "rev"                  => "Rev",
        "rev_mrs"              => "Rev & Mrs",
        "rev_canon"            => "Rev Canon",
        "rev_dr"               => "Rev Dr",
        "senator"              => "Senator",
        "sgt"                  => "Sgt",
        "sheriff"              => "Sheriff",
        "sir"                  => "Sir",
        "sir_lady"             => "Sir & Lady",
        "sqr_leader"           => "Sqr. Leader",
        "the_earl_of"          => "The Earl of",
        "the_hon"              => "The Hon",
        "the_hon_dr"           => "The Hon Dr",
        "the_hon_lady"         => "The Hon Lady",
        "the_hon_lord"         => "The Hon Lord",
        "the_hon_mrs"          => "The Hon Mrs",
        "the_hon_sir"          => "The Hon Sir",
        "the_honourable"       => "The Honourable",
        'the_right_honourable' => 'The Right Honourable',
        'the_most_honourable'  => 'The Most Honourable',
        "the_rt_hon"           => "The Rt Hon",
        "the_rt_hon_dr"        => "The Rt Hon Dr",
        "the_rt_hon_lord"      => "The Rt Hon Lord",
        "the_rt_hon_sir"       => "The Rt Hon Sir",
        "the_rt_hon_visc"      => "The Rt Hon Visc",
        "viscount"             => "Viscount",
        'master'               => 'Master',
        'mister'               => 'Mister',
        'mx'                   => 'Mx',
        'gentleman'            => 'Gentleman',
        'sire'                 => 'Sire',
        'mistress'             => 'Mistress',
        'captain'              => 'Captain',
        'esq'                  => 'Esquire',
        'excellency'           => 'Excellency',
        'qc'                   => 'Queen\'s Counsel',
        'kc'                   => 'King\'s Counsel',
        'eur_eng'              => 'European Engineer',
        'chancellor'           => 'Chancellor',
        'vice_chancellor'      => 'Vice Chancellor',
        'principal'            => 'Principal',
        'president'            => 'President',
        'warden'               => 'Warden',
        'dean'                 => 'Dean',
        'regent'               => 'Regent',
        'rector'               => 'Rector',
        'provost'              => 'Provost',
        'director'             => 'Director',
        'chief_executive'      => 'Chief Executive',
        'other'                => 'Other',
    ],

    'media_types' => [
        'video'  =>  'Video',
        'image'  =>  'Image',
        'audio'  =>  'Audio',
    ],

    'article_formats' => [
        'gallery' => 'Gallery Post',
        'slider'  => 'Slider Post',
        'mixed'   => 'Mixed Content',
        'video'   => 'Video',
        'audio'   => 'Audio',
        'link'    => 'Link',
        'code'    => 'Code',
        'text'    => 'Text',
    ],

    'article_types' => [
        'case_study' => "Case Study",
        'project'    => "Project",
        'article'    => "Article",
        'journal'    => "Journal",
        'gallery'    => "Gallery",
        'company'    => "Company",
        'blog'       => "Blog",
        'news'       => "News",
        'vlog'       => "Video Blog (Vlog)",
        'faq'        => "FAQ",
        'add_custom' => 'Add Custom', // this allows us to create custom types
    ],

    'mailing_protocols' => [
        'smtp' => 'SMTP',
        'mail' => 'Mail',
    ],

    'auth_options' => [
        'signout' => [
            'term' =>  'exit',
            'reasons' => [
                'session_error' => 'Session Error',
                'user_request'  => 'User Request',
                'signin_error'  => 'Signin Error',
                'session_idle'  => 'Session Idle',
            ],
        ],
        'security' =>  [
            'captcha' => [
                'key'  => 'captcha',
                'name' => 'Captcha'
            ],
            'flagged' => [
                'key'  => 'flagged',
                'name' => 'Flagged'
            ],
            'banned' => [
                'key'  => 'banned',
                'name' => 'Banned'
            ],
            'blocked' => [
                'key'  => 'blocked',
                'name' => 'Blocked'
            ],
            'throttle' => [
                'key'  => 'throttle',
                'name' => 'Throttle'
            ],
            'attempt' => [
                'key'  => 'attempt',
                'name' => 'Attempt'
            ],
            'session_idle' => [
                'key'  => 'session_idle',
                'name' => 'Session Idle'
            ],

        ],
    ],

    'profession_types' => [
        'public_service' => 'Public Service',
        'transport'      => 'Transport',
        'academia'       => 'Academia',
        'cultural'       => 'Cultural',
        'industry'       => 'Industry',
        'nursing'        => 'Nursing',
        'science'        => 'Science',
        'other'          => 'Other',
        'none'           => 'None',
    ],

    'company_registration_types' => [
        'LLC' => 'Limited liability company',
        'LLP' => 'Limited Liability Partnership',
        'PMC' => 'Property management company',
        'CLG' => 'Companies Limited by Guarantee',
        'CIC' => 'Community Interest Company',
        'CIO' => 'Charitable Incorporated Organisation',
        'RTM' => 'Right to manage company',
        'LTD' => 'Public company (Ltd)',
        'INC' => 'Incorporated',
        'SOC' => 'State-owned company (SOC)',
        'EC'  => 'External company ',
        'NPC' => 'Non-profit company (NPC) ',
        'PLC' => 'Public Limited Companies (PLC)',
        'PTY' => 'Private company (Pty) Ltd',
        'UC'  => 'Unlimited company',
    ],

    # https://www.recruiter.com/careers/
    'occupation_types' => [
        'agriculture_food_and_natural_resources'    => 'Agriculture, Food and Natural Resources',
        'architecture_and_construction'             => 'Architecture and Construction',
        'arts_video_tech_and_communication'         => 'Arts, Audio/Video Technology and Communications',
        'business_management_and_administration'    => 'Business Management and Administration',
        'education_and_training'                    => 'Education and Training',
        'finance'                                   => 'Finance',
        'government_and_public_administration'      => 'Government and Public Administration',
        'health_science'                            => 'Health Science',
        'hospitality_and_tourism'                   => 'Hospitality and Tourism',
        'human_services'                            => 'Human Services',
        'information_technology'                    => 'Information Technology',
        'law_public_safety_correction_and_security' => 'Law, Public Safety, Corrections and Security',
        'manufacturing'                             => 'Manufacturing',
        'marketing_sales_and_service'               => 'Marketing, Sales and Service',
        'science_tech_engineering_and_mathematics'  => 'Science, Technology, Engineering and Mathematics',
        'transportation_distribution_and_logistics' => 'Transportation, Distribution and Logistics',
    ],

    # http:// hbswk.hbs.edu/industries/
    'industry_types' => [
        'agriculture'                           => 'Agriculture',
        'accounting'                            => 'Accounting',
        'advertising'                           => 'Advertising',
        'aerospace'                             => 'Aerospace',
        'aircraft'                              => 'Aircraft',
        'airline'                               => 'Airline',
        'apparel_accessories'                   => 'Apparel & Accessories',
        'automotive'                            => 'Automotive',
        'banking'                               => 'Banking',
        'broadcasting'                          => 'Broadcasting',
        'brokerage'                             => 'Brokerage',
        'biotechnology'                         => 'Biotechnology',
        'call_centers'                          => 'Call Centers',
        'cargo_handling'                        => 'Cargo Handling',
        'chemical'                              => 'Chemical',
        'computer'                              => 'Computer',
        'consulting'                            => 'Consulting',
        'consumer_products'                     => 'Consumer Products',
        'cosmetics'                             => 'Cosmetics',
        'defense'                               => 'Defense',
        'department_stores'                     => 'Department Stores',
        'education'                             => 'Education',
        'electronics'                           => 'Electronics',
        'energy'                                => 'Energy',
        'entertainment_and_leisure'             => 'Entertainment & Leisure',
        'executive_search'                      => 'Executive Search',
        'financial_services'                    => 'Financial Services',
        'food_beverage_and_tobacco'             => 'Food, Beverage & Tobacco',
        'grocery'                               => 'Grocery',
        'health_care'                           => 'Health Care',
        'internet_publishing'                   => 'Internet Publishing',
        'investment_banking'                    => 'Investment Banking',
        'legal'                                 => 'Legal',
        'manufacturing'                         => 'Manufacturing',
        'motion_picture_video'                  => 'Motion Picture & Video',
        'music'                                 => 'Music',
        'newspaper_publishers'                  => 'Newspaper Publishers',
        'online_auctions'                       => 'Online Auctions',
        'pension_funds'                         => 'Pension Funds',
        'pharmaceuticals'                       => 'Pharmaceuticals',
        'private_equity'                        => 'Private Equity',
        'publishing'                            => 'Publishing',
        'real_estate'                           => 'Real Estate',
        'retail_and_Wholesale'                  => 'Retail & Wholesale',
        'securities_and_commodity_exchanges'    => 'Securities & Commodity Exchanges',
        'service'                               => 'Service',
        'soap_and_detergent'                    => 'Soap & Detergent',
        'software'                              => 'Software',
        'sports'                                => 'Sports',
        'technology'                            => 'Technology',
        'telecommunications'                    => 'Telecommunications',
        'television'                            => 'Television',
        'transportation'                        => 'Transportation',
        'trucking'                              => 'Trucking',
        'venture_capital'                       => 'Venture Capital',
        'other'                                 => 'Other',
        'none'                                  => 'None',
    ],

    'career_types' => [
        'accountant'                        =>  'Accountant',
        'actuarie'                          =>  'Actuarie',
        'advocate'                          =>  'Advocate',
        'agriculturist'                     =>  'Agriculturist',
        'air_traffic_controller'            =>  'Air Traffic Controller',
        'aircraft_pilot'                    =>  'Aircraft Pilot',
        'archaeologist'                     =>  'Archaeologist',
        'architect'                         =>  'Architect',
        'artist'                            =>  'Artist',
        'astronomer'                        =>  'Astronomer',
        'audiologist'                       =>  'Audiologist',
        'biologist'                         =>  'Biologist',
        'botanist'                          =>  'Botanist',
        'chemist'                           =>  'Chemist',
        'clergy'                            =>  'Clergy',
        'dentist'                           =>  'Dentist',
        'designer'                          =>  'Designer',
        'ecologist'                         =>  'Ecologist',
        'economist'                         =>  'Economist',
        'engineer'                          =>  'Engineer',
        'english'                           =>  'English',
        'firefighter'                       =>  'Firefighter',
        'geneticist'                        =>  'Geneticist',
        'geologist'                         =>  'Geologist',
        'historian'                         =>  'Historian',
        'immunologist'                      =>  'Immunologist',
        'interpreter'                       =>  'Interpreter',
        'journalist'                        =>  'Journalist',
        'judge'                             =>  'Judge',
        'language_professional'             =>  'Language Professional',
        'lawyer'                            =>  'Lawyer',
        'librarian'                         =>  'Librarian',
        'mathematician'                     =>  'Mathematician',
        'meteorologist'                     =>  'Meteorologist',
        'midwife'                           =>  'Midwife',
        'military_officer'                  =>  'Military Officer',
        'none'                              =>  'None',
        'nurse'                             =>  'Nurse',
        'oceanographer'                     =>  'Oceanographer',
        'optometrist'                       =>  'Optometrist',
        'other'                             =>  'Other',
        'paramedic'                         =>  'Paramedic',
        'pathologist'                       =>  'Pathologist',
        'pharmacist'                        =>  'Pharmacist',
        'pharmacologist'                    =>  'Pharmacologist',
        'philosopher'                       =>  'Philosopher',
        'physician_public_service'          =>  'Physician (Public Service)',
        'physicist'                         =>  'Physicist',
        'physiotherapist'                   =>  'Physiotherapist',
        'police_officer'                    =>  'Police Officer',
        'property_appraiser_and_valuer'     =>  'Property Appraiser And Valuer',
        'psychologist'                      =>  'Psychologist',
        'scientist'                         =>  'Scientist',
        'sea_captain'                       =>  'Sea Captain',
        'search_and_rescuer'                =>  'Search And Rescuer',
        'social_worker'                     =>  'Social Worker',
        'solicitor'                         =>  'Solicitor',
        'speech_language_pathologist'       =>  'Speech-language Pathologist',
        'statistician'                      =>  'Statistician',
        'surgeon'                           =>  'Surgeon',
        'surveyor'                          =>  'Surveyor',
        'teacher'                           =>  'Teacher',
        'urban_planner'                     =>  'Urban Planner',
        'veterinarian'                      =>  'Veterinarian',
        'virologist'                        =>  'Virologist',
        'zoologist'                         =>  'Zoologist',
    ],

    'copyright_texts' => [
        'declaration' => "All brands, logos and trademarks are products and &copy; to their respective owners.",
        'made_in'     => "Made in Nairobi, Kenya",
        'rights'      => "All Rights Reserved",
        'pride'       => "Carefully, Beautifully handcrafted with lot's of LOVE & PRIDE in Nairobi, KENYA",
        'year'        => date('Y'),
    ],

    /**
     * #######################################################################
     *
     * MENU SETTINGS
     *
     * #######################################################################
     *
     */

    'menu_locations' => [
        'secondary' => 'Secondary',
        'primary'   => 'Primary',
        'inline'    => 'Inline',
        'mini'      => 'Mini',
        'main'      => 'Main',
        'side'      => 'Side',
    ],

    // link types
    'link_types' => [
        'internal' => 'Internal Link',
        'external' => 'External Link',
    ],

    // anchor types
    'anchor_types' => [
        'parent'  => 'Parent Link',
        'article' => 'Article Link',
    ],

    // possible link to types
    // in the order of location to page
    'link_to'     => [
        'home'       => 'Home',
        'users'      => 'Users',
        'blog'       => 'Blog',
        'articles'   => 'Article',
        'companies'  => 'Companies',
        'about'      => 'About',
        'contact'    => 'Contact',
        'api'        => 'API',
        'add_custom' => 'Add Custom', // this allows us to create custom types
    ],

    /**
     * #######################################################################
     *
     * SEARCH
     *
     * #######################################################################
     *
     */
    'search_options' => [
        'term' => 'Search',
        'key'  => 'q',
    ],

    /**
     * #######################################################################
     *
     * Address and Contact types
     *
     * #######################################################################
     *
     */
    'address_groups' => [
        'Service_location' => 'Service location',
        'mailing'          => 'Mailing',
        'home'             => 'Home',
        'work'             => 'Work',
        'office'           => 'Office',
        'family'           => 'Family',
        'personal'         => 'Personal',
        'shipping'         => 'Shipping',
        'billing'          => 'Billing',
    ],

    /**
     * #######################################################################
     *
     * SUPPORT & TICKETING
     *
     * #######################################################################
     *
     */
    'helpdesk_options' => [
        'departments' => [
            'billing'   => 'Billing',
            'sales'     => 'Sales',
            'technical' => 'Technical',
            'support'   => 'Support',
            'r_and_d'   => 'Research & Design',
        ],
        'statuses'       => [
            'new'                    => 'New',
            'open'                   => 'Open',
            'assigned'               => 'Assigned',
            'pending'                => 'Pending',
            'on_hold'                => 'On-hold',
            'closed'                 => 'Closed',
            'waiting_on_customer'    => 'Waiting on customer',
            'waiting_on_support'     => 'Waiting on support',
            'waiting_on_third_party' => 'Waiting on Third-party',
            'under_review'           => 'Under review',
            'locked'                 => 'Locked',
            'resolved'               => 'Resolved',
            'merged'                 => 'Merged',
            'spam'                   => 'Spam',
        ],
        'statuses_short' => [
            'new_unassigned'                => "New unassigned",
            'new_escalated'                 => "New escalated",
            'assigned_under_review'         => "Assigned under review",
            'assigned_pending_third_party'  => "Assigned pending 3rd party",
            'assigned_pending_customer'     => "Assigned pending customer",
            'assigned_pending_agent'        => "Assigned pending agent",
            'resolved'                      => "Resolved",
            'closed'                        => "Closed",
        ],
        'priorities'  => [
            'low'      => 'Low',
            'normal'   => 'Normal',
            'medium'   => 'Medium',
            'high'     => 'High',
            'urgent'   => 'Urgent',
            'critical' => 'Critical',
            'pending'  => 'Pending',
            'blocker'  => 'Blocker',
        ],
        'priorities_short'  => [
            'urgent_priority_1' => "Priority 1",
            'normal_priority_2' => "Priority 2",
            'low_priority_3'    => "Priority 3",
        ],

    ],


    /**
     * #######################################################################
     *
     * NOTIFICATION OPTIONS
     *
     * #######################################################################
     *
     */
    'notification_options' => [
        'frequency' => [
            'frequency' => [
                'everyday' => "Everyday",
                'weekly'   => "Weekly",
                'never'    => "Never",
            ],
            'time' => [
                'when_online' => "Only when I'm online",
                'immediately' => "Immediately",
                'midnight'    => "Midnight (00:00)",
                'always'      => "Always",
                '6_am'        => "6 AM",
                '9_am'        => "9 AM",
                'noon'        => "Noon (12:00)",
                '4_pm'        => "4 PM",
                '6_pm'        => "6 PM",
                '9_pm'        => "8 PM",
            ],
        ],
        'channels'  => [
            'email'   => "Email",
            'sms'     => "SMS",
            'browser' => "Browser",
            'push'    => "Push",
        ],
    ],

];
