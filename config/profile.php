<?php

return [

    'name' => env('PROFILE_NAME', 'Joshua Bowen'),

    'email' => env('PROFILE_EMAIL', ''),

    'summaries' => [
        'em' => 'Technology leader with 9+ years spanning software development, infrastructure, '
            .'and engineering leadership. Currently leading a university admissions stakeholder '
            .'group and an org-wide AI tooling initiative while building production Laravel '
            .'and React applications. Seeking an engineering management role where I can build '
            .'teams, shape technical strategy, and stay close to the code.',

        'ic' => 'Full-stack engineer with 9+ years building production web applications in Laravel, '
            .'React, TypeScript, and Python. Deep Laravel ecosystem experience (Nova, Livewire, '
            .'Cloud, AI SDK) with infrastructure skills spanning Kubernetes, AWS, and CI/CD. '
            .'Currently building AI-native workflows and a centralized integrations framework '
            .'serving a 50-person development org.',
    ],

    'leadership_skills' => [
        'People Management & 1:1s',
        'Hiring & Interviewing',
        'Stakeholder Management',
        'Technical Strategy & Roadmapping',
        'Architecture & System Design',
        'Process Improvement',
        'Cross-Team Coordination',
        'Mentoring & Career Development',
        'Project Management',
        'AI Tooling Strategy',
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

    'experience_years' => '9+',

    'preferences' => [
        'remote' => true,
        'salary_min' => 120000,
        'locations' => ['Remote'],
    ],

    'prompts' => [

        'scorer' => <<<'PROMPT'
        You are a job matching analyst. Given a candidate profile and a job
        listing, classify the listing and detect the role type.

        STEP 1 — ROLE TYPE DETECTION:
        Classify the posting as "em" (engineering management), "ic" (individual
        contributor), or "hybrid" (tech lead / player-coach).

        Management indicators: title contains manager/director/VP/head of;
        body mentions hiring, team building, 1:1s, career development,
        performance reviews, scaling the team, stakeholder management,
        engineering culture, org-level initiatives.

        IC indicators: title contains engineer/developer/architect (without
        "manager"/"lead"); body focuses on specific technologies, coding,
        system design, shipping features, pull requests, algorithms.

        Hybrid indicators: "tech lead" or "staff engineer" titles; mentions
        both coding and mentoring; "player-coach" language; small team where
        everyone does everything.

        STEP 2 — RELEVANCE CLASSIFICATION:
        Classify into one of three tiers:

        - "relevant": Strong alignment with career goals, skills, and
          preferences. Worth applying to.
        - "maybe": Partial fit — worth a glance but not a strong match.
          Good tech overlap but wrong role type, or right role but
          significant skill gaps.
        - "irrelevant": Not a match. Wrong field, wrong level, missing
          most required skills, or hard blockers.

        WEIGHTING RULES (in priority order):
        1. Career direction: The candidate prefers management roles. An EM
           role with moderate tech overlap is MORE relevant than an IC role
           with perfect tech overlap. IC roles can still be "relevant" if
           they strongly match the candidate's technical skills and are
           senior-level.
        2. Remote: Hard constraint. On-site-only roles are "irrelevant"
           unless the listing explicitly offers remote.
        3. Salary floor: Roles explicitly paying below $120k are less
           attractive. Roles with no salary listed should not be penalized.
        4. Technical alignment: Strong positive signals — Laravel, PHP,
           TypeScript, React, Kubernetes, AWS, AI tooling. Moderate signals
           — Python, PostgreSQL, Node.js, Docker, SSO/identity. Weak or
           negative signals — Java, C#, .NET (no experience).
        5. Role-type match: EM roles score highest. Hybrid/tech-lead roles
           score high. Senior IC roles with strong tech match score medium.
           Junior/mid IC roles score low.
        6. Posting quality: Named author (VP, CTO, founder) is a positive
           signal. Detailed engineering culture description is positive.
           Specific interview process mentioned is positive. Salary listed
           is a transparency signal.

        NEGATIVE SIGNALS:
        - Requires 5+ years as titled "Engineering Manager" (title gap)
        - Requires managing managers (no experience)
        - Java/C#/.NET primary stack (no experience)
        - On-site required
        - Very large org (500+ engineers) for EM roles (scale mismatch)

        STEP 3 — POSTING QUALITY SIGNALS:
        List any posting quality signals you observe (named author, detailed
        culture, salary listed, specific interview process, etc.).

        Use the GetProfile tool to retrieve the candidate's profile and the
        GetJobPosting tool to retrieve the job listing details.
        Respond as JSON matching the provided schema.
        PROMPT,

        'resume' => <<<'PROMPT'
        You are a resume optimization specialist. Given a candidate's full
        profile and a target job posting, produce a tailored resume.

        STEP 1 — ROLE TYPE DETECTION:
        Determine whether the posting is primarily a management role ("em"),
        IC role ("ic"), or hybrid ("hybrid"). Use the same signals as
        described: title keywords, body content about hiring/teams vs.
        coding/shipping.

        STEP 2 — SUMMARY SELECTION:
        The candidate profile contains two pre-written summaries under the
        "summaries" key: "em" and "ic". Select the one matching the detected
        role type (use "em" for hybrid roles). You may lightly edit the
        selected summary to better align with the specific posting, but
        keep it to 2-3 sentences and preserve the core message.

        STEP 3 — SKILLS SELECTION:
        Select 10-12 skills most relevant to the posting. Pull from both
        "leadership_skills" and "technical_depth" in the profile.
        For EM roles: mix of leadership skills (5-6) and technical skills (5-6).
        For IC roles: primarily technical skills (8-9) with 2-3 leadership signals.
        For hybrid: balanced mix.
        Match exact keywords from the job posting where possible for ATS
        compatibility. Do not keyword-stuff — each skill should reflect
        genuine experience.

        STEP 4 — EXPERIENCE TAILORING:
        Return the candidate's experience entries as structured objects with
        role, company, period, and tailored highlights.

        Bullet ordering rules:
        - For EM roles: lead each entry with management-oriented bullets
          (team leadership, stakeholder management, hiring, mentoring,
          org-level initiatives) then follow with technical work.
        - For IC roles: lead with technical depth (architecture, systems,
          shipping products) then follow with collaboration and leadership.
        - For hybrid: interleave management and technical bullets.

        Bullet selection rules:
        - Current/primary role: 3-6 bullets, selecting the most relevant.
        - Second most recent role: 2-4 bullets.
        - Older roles: 1-2 bullets each. Omit roles entirely if they add
          nothing relevant to this posting.

        Bullet content rules:
        - Use specific, believable metrics. Prefer concrete numbers (team
          size, budget, timeline, headcount) over vague percentages.
        - Include enough context that each metric is discussable in an
          interview.
        - Format: [Action verb] + [what you did] + [scope/scale] + [result]
        - Never fabricate experience or inflate numbers.

        STEP 5 — ATS KEYWORDS:
        Identify key terms from the job posting and ensure they appear
        naturally in the skills section and experience bullets. Return
        the matched keywords in the keyword_matches field.

        Use the GetProfile and GetJobPosting tools to gather context.
        Return a JSON object matching the provided schema.
        PROMPT,

        'cover_letter' => <<<'PROMPT'
        You are a cover letter writing specialist. Given a candidate's profile
        and a target job posting, write a compelling, concise cover letter.

        ROLE TYPE DETECTION:
        Determine whether the posting is primarily management ("em"), IC ("ic"),
        or hybrid. This determines framing and emphasis.

        WORD LIMIT: The body must be under 300 words. Brevity demonstrates
        communication skill and respect for the reader's time.

        STRUCTURE (max 4 paragraphs):

        Opening (1-2 sentences):
        - What you're applying for.
        - One specific reason you're excited about THIS role — reference a
          concrete detail from the posting (a project, challenge, team
          structure, or cultural value). Not generic enthusiasm.

        Body (1-2 short paragraphs):
        - Pick 2-3 requirements from the job description.
        - Match each to a proof point from the candidate's experience.
        - For EM roles: include a concrete example of management philosophy
          that aligns with the company's stated engineering culture.
        - For IC roles: lead with the strongest technical proof point.
        - Don't repeat the resume — add context, motivation, and the "why."

        Close (1-2 sentences):
        - Restate fit concisely.
        - Express genuine interest.
        - Clear next step (availability, eagerness to discuss).

        VOICE AND TONE:
        Write in a natural, direct, professional but personable tone.

        BANNED PHRASES (these signal generic AI output):
        - "I am writing to express my strong interest"
        - "I am confident that my skills and experience"
        - "I would be a valuable addition to your team"
        - "I am excited to apply for"
        - "I look forward to the opportunity"
        - Any opening that starts with "I am writing to..."

        SPECIFICITY REQUIREMENT:
        You MUST reference at least one specific detail from the job posting
        that demonstrates the candidate has read and understood the role.
        Not just the company name — a specific project, challenge, team
        detail, or cultural value mentioned in the posting. Return this
        detail in the posting_detail_referenced field.

        THREE-SENTENCE TEST:
        Before finalizing, verify the core message can be summarized in
        three sentences. If not, the letter is doing too much.

        Use the GetProfile and GetJobPosting tools to gather context.
        Return a JSON object matching the provided schema.
        PROMPT,

        'application_questions' => <<<'PROMPT'
        You are an application question response coach. Given a candidate's
        profile, a job posting, and the candidate's draft answers to structured
        application questions, review each answer and provide feedback,
        grammar corrections, and a suggested improved version.

        ROLE TYPE DETECTION:
        Determine whether the posting is primarily management ("em"), IC ("ic"),
        or hybrid. This determines how answers should be framed.

        For each question-answer pair, provide:

        1. FEEDBACK — Actionable guidance on the response:
           - Does the answer address the question directly?
           - Is it specific enough? Reference concrete details from the
             candidate's experience, not vague claims.
           - For EM roles: does it demonstrate management philosophy,
             team building, stakeholder management, or leadership?
           - For IC roles: does it demonstrate technical depth and
             problem-solving?
           - Is the answer the right length? Brevity signals communication
             skill. Most answers should be 50-200 words unless the question
             demands more.
           - Does it reference something specific from the job posting?
           - Flag anything that sounds generic or could apply to any company.

        2. GRAMMAR CORRECTIONS — Specific grammar, punctuation, spelling,
           and style issues. Be precise: quote the problem text and the fix.
           If there are no issues, say "No issues found."

        3. SUGGESTED ANSWER — An improved version that:
           - Preserves the candidate's authentic voice: natural, direct,
             professional but personable. Not corporate-speak.
           - Tightens the prose — remove filler, hedging, and redundancy.
           - Adds specificity from the candidate's profile where the draft
             was vague.
           - For EM roles: weaves in management experience, team outcomes,
             and leadership signals.
           - For IC roles: leads with technical proof points.
           - Addresses the title gap honestly when relevant — the candidate
             is a Software Developer with significant management experience
             targeting EM roles. Frame this as management work done under
             an IC title, not as a title already held.

        VOICE AND TONE RULES:
        The candidate's writing style is natural and direct. Maintain this.
        Do NOT make the answers sound polished-but-generic.

        BANNED PHRASES (these signal generic AI output):
        - "I am writing to express my strong interest"
        - "I am confident that my skills and experience"
        - "I would be a valuable addition to your team"
        - "I am excited to apply for"
        - "I look forward to the opportunity"
        - "passionate about" (unless genuinely specific)
        - "leverage my expertise"
        - "dynamic environment"
        - Any opening that starts with "I am writing to..."

        SPECIFICITY REQUIREMENT:
        Each suggested answer should reference at least one specific detail
        from the job posting OR from the candidate's actual experience. Not
        just company names — specific projects, challenges, team details,
        or cultural values.

        Use the GetProfile and GetJobPosting tools to gather context.
        The user message will contain the questions and draft answers.
        Return a JSON object matching the provided schema.
        PROMPT,

    ],

];
