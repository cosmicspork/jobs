<?php

return [

    'name' => env('PROFILE_NAME', 'Joshua Bowen'),

    'title' => env('PROFILE_TITLE', 'Engineering Manager'),

    'summary' => 'Technology leader with 10 years spanning software development, systems administration, DevOps, '
        .'and vendor integrations, seeking an engineering management role. '
        .'Experienced in hiring, 1:1s, stakeholder management, cross-team coordination, and technical strategy. '
        .'Sole manager of a university admissions stakeholder group — running meetings, managing feature requests, '
        .'bug reports, project status, and process improvement discovery. '
        .'Designed and championed a centralized integrations framework adopted by the dev team, '
        .'replacing 50+ integrations scattered across a legacy codebase. '
        .'Led an AI tooling task force for a 50-person development org — administered a developer survey, '
        .'conducted structured interviews, and presented recommendations at an org-wide AI summit (Oct 2025). '
        .'Co-founded a startup (PakPak) and ran the business side (incorporation, accounting, legal, fundraising). '
        .'Built and managed a small-business accelerator program including curriculum development, '
        .'expert recruitment, and client consulting. '
        .'B.S. in Entrepreneurship, M.S. in IT Innovation, Graduate Certificate in Cybersecurity. '
        .'Passionate about ideation, strategy, architecture, and vision — wants to lead teams, not just write code.',

    'skills' => [
        'People Management',
        'Hiring & Interviewing',
        'Stakeholder Management',
        'Project Management',
        'Technical Strategy',
        'Architecture & System Design',
        'Process Improvement',
        'Cross-Team Coordination',
        'PHP',
        'Laravel',
        'TypeScript',
        'React',
        'Python',
        'Kubernetes',
        'Docker',
        'AWS',
        'CI/CD',
        'SSO (SAML/OIDC)',
        'AI Tooling',
        'Laravel AI SDK',
    ],

    'experience' => [
        [
            'role' => 'Software Developer',
            'company' => 'University of Nebraska System',
            'period' => 'June 2022 - Present',
            'highlights' => [
                'Sole manager of the admissions stakeholder group — scheduling and running meetings, project status updates, feature requests, bug reports, Jira space administration, and process improvement discovery through shadowing.',
                'Designed a centralized integrations framework in Laravel that auto-detects job blueprints from PHP files, with metadata-driven docs generation, execution, notifications, and retries. Got buy-in from the dev team to replace 50+ integrations in a legacy PHP 7.4/Zend app.',
                'Led AI tooling task force for a 50-person org — designed and administered a developer survey, conducted structured interviews, synthesized recommendations for leadership. Presented at an org-wide AI summit (Oct 2025).',
                'Built a React/TypeScript + Laravel Nova monorepo serving a reading education platform. Nova as CMS for structured word, audio, and sentence data; backend serves an OpenAPI-specified API; designed to support future mobile apps.',
                'Maintains a large, complex Laminas + legacy PHP 7.4 admissions application.',
                'Automated Laravel deployments using Kubernetes, Flux, Helm, Kustomize, kubectl, and GitLab CI in AWS.',
                'Manages SSO infrastructure — SAML/Shibboleth (IdP and SP) for all Laravel apps, OIDC proxy in Coder dev environments.',
                'Vendor data integrations (SFTP, API, S3, WebDAV) in and out of a MSSQL data warehouse. SIS integrations with PeopleSoft and Banner. Salesforce API integration for student success hub.',
                'Administers team Jira and GitLab environments. Previously administered GitHub teams and wrote GitHub Actions.',
            ],
        ],
        [
            'role' => 'Senior Workstation Support Associate',
            'company' => 'University of Nebraska Omaha',
            'period' => 'June 2020 - June 2022',
            'highlights' => [
                'Managed IT operations for a 20,000 sqft community engagement center serving ~80 staff — conference rooms, convention space, classrooms, offices, digital signage, and building automation.',
                'Supervised two student workers. Wrote a job description, posted internally, conducted screening calls and interviews, and hired one report. Ran monthly 1:1 check-ins focused on learning and development opportunities.',
                'Supported multiple departments across campus. Administered servers, led Box-to-SharePoint/OneDrive migration, and wrote PowerShell automation scripts for file deduplication and shortcut cleanup.',
                'Volunteered for overtime supporting athletics events — AV equipment, point of sale, live streaming, arena IPTV, and video production servers.',
            ],
        ],
        [
            'role' => 'Co-Founder & CTO',
            'company' => 'PakPak Inc',
            'period' => 'March 2018 - March 2019',
            'highlights' => [
                'Won 3rd place at TechStars Startup Weekend Phoenix, then incorporated as a C-corp. Handled formation documents, accounting, and legal.',
                'Won seed funding at ASU Venture Devils startup competition. Used it to fund full-time development for 4 months.',
                'Built multiple prototypes (Python/Django, React Native) and a beta product. Dissolved after a failed acquisition attempt.',
            ],
        ],
        [
            'role' => 'Volunteer & Accelerator Program Builder',
            'company' => 'WingSpace CoWorking',
            'period' => 'April 2017 - March 2020',
            'highlights' => [
                'Co-built a small-business accelerator program with the owner and a professional facilitator. Developed curriculum, recruited local business experts for panels, and consulted with clients on technology.',
                'First two clients successfully completed the program (Feb 2020). WingSpace and the accelerator were acquired by a private investor in Q4 2021.',
                'Helped open the coworking space — business strategy, marketing, community building, network deployment, and event hosting.',
            ],
        ],
        [
            'role' => 'Web Systems Administrator',
            'company' => 'Yavapai College',
            'period' => 'May 2019 - March 2020',
            'highlights' => [
                'Supported a small dev team. Wrote infrastructure automation in PowerShell, established IaC with Puppet.',
                'Built a custom reverse proxy in Go. Wrote end-to-end and load tests (Python, JMeter) for CI/CD pipelines.',
                'Automated 90% of a legacy server cluster deployment (Windows Server 2008 to 2016 migration).',
            ],
        ],
        [
            'role' => 'Freelance Software Engineer',
            'company' => 'Self-employed',
            'period' => 'December 2016 - Present',
            'highlights' => [
                'Built websites and web applications using WordPress, Django, Laravel, Node, Rust, PHP, Python, JavaScript. Deployed to AWS, Heroku, Netlify, shared hosting, and bare metal.',
                'Notable projects: HIPAA-compliant patient portal, progressive web app for reading education.',
                'Set up CI/CD pipelines, automated testing, log collection, and continuous monitoring with alerts.',
                'Coached individuals on cybersecurity, automation, and business tools. Consulted with small business owners on marketing, process automation, and security training.',
            ],
        ],
        [
            'role' => 'Instructor',
            'company' => 'Prescott Valley Public Library',
            'period' => 'June 2017 - August 2018',
            'highlights' => [
                'Taught public technology classes to primarily senior audiences. Facilitated an adult code club using Free Code Camp. Hosted drop-in tech help with a student volunteer.',
            ],
        ],
    ],

    'education' => [
        'M.S. in IT Innovation, University of Nebraska Omaha (May 2025)',
        'Graduate Certificate in Cybersecurity, University of Nebraska Omaha (Dec 2025)',
        'B.S. in Entrepreneurship, Northern Arizona University (May 2018)',
        'A.S. in Information Technology, Metropolitan Community College (Dec 2016)',
    ],

    'technical_depth' => [
        'languages' => ['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust', 'PowerShell', 'Bash'],
        'frameworks' => ['Laravel', 'Laminas', 'Django', 'React', 'Node.js', 'Livewire', 'AlpineJS'],
        'laravel_ecosystem' => ['Nova', 'Cloud', 'Nightwatch', 'AI SDK', 'Livewire'],
        'databases' => ['PostgreSQL', 'MySQL', 'SQLite', 'MSSQL', 'Oracle'],
        'caching' => ['Redis', 'Memcached', 'Valkey'],
        'devops' => ['Kubernetes', 'Helm', 'Flux', 'Kustomize', 'Docker', 'Podman', 'GitLab CI', 'GitHub Actions'],
        'cloud' => ['AWS', 'Azure'],
        'identity' => ['SAML/Shibboleth', 'OIDC', 'CAS'],
        'integrations' => ['SFTP', 'WebDAV', 'S3', 'OpenAPI', 'Salesforce API', 'Jira API', 'PeopleSoft', 'Banner'],
        'ai_tools' => ['Claude Code', 'Codex', 'OpenCode', 'Laravel AI SDK'],
    ],

    'experience_years' => 10,

    'preferences' => [
        'remote' => true,
        'salary_min' => 70000,
        'locations' => ['Remote'],
    ],

];
