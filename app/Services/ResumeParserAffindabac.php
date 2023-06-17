<?php
namespace App\Services;

use App\Models\User;
use App\Models\Talent;
use App\Models\TalentEducation;
use App\Models\TalentExperience;
use App\Models\SkillMaster;
use App\Models\TalentSkill;
use App\Models\TalentExperienceSkill;

class ResumeParserAffinda
{
    private $path;
    private $talent_id;
    private $save;
    // private $talent;

    private $parsed = false;
    private $parseData = [];

    function __construct($path, $talent_id = 0, $save = false){
        $this->path = $path;
        $this->talent_id = $talent_id;
        $this->save = $save;
        if($this->parsed === false){
            $this->parseResume();
        }
    }

    public function parseResume()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.affinda.com/v1/resumes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($this->path)),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.env('AFFINDA_KEY'),
                'Accept-Encoding: gzip, deflate',
                'accept: */*'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $profileData = json_decode($response);
        // return $this->dataParsingFromApiData($profileData->data);
        $this->parsed = true;
        $this->parseData = $profileData->data;
    }

    public function getAllData()
    {
        $primaryDetails = $this->fetchPrimaryDetailsFromResumeData();
        $skills = $this->fetchSkillsFromResumeData();
        $experience = $this->fetchExperienceFromResumeData();
        $educationDetails = $this->fetchEducationDetailsFromResumeData();
        $personalLocation = $this->fetchLocatiobDetailsFromResumeData();
        $certificateDetails = $this->fetchCertificateDetailsFromResumeData();
        $experienceSkills = $this->fetchExperienceSkillDetails();
        // $sections = $this->fetchSections();
        return [
            'primaryDetails' => $primaryDetails,
            'skills' => $skills,
            'experience' => $experience,
            'educationDetails' => $educationDetails,
            'personalLocation' => $personalLocation,
            'certificateDetails' => $certificateDetails,
            'experienceSkills' => $experienceSkills,
        ];
    }

    public function fetchPrimaryDetailsFromResumeData()
    {
        $resumeData = $this->parseData;
        $primaryDetails = [];
        $primaryDetails['name'] = $resumeData->name->raw ?? null;
        $primaryDetails['dob'] = $resumeData->dateOfBirth ?? null; // there is no column for this in database
        $primaryDetails['emails'] = $this->fetchAllEmails() ?? null;
        $primaryDetails['phoneNumbers'] = $this->fetchAllPhoneNumbers() ?? null;
        if($resumeData->objective == '' && $resumeData->summary != ''){
            $primaryDetails['objective'] = $resumeData->summary ?? null;
        } else {
            $primaryDetails['objective'] = $resumeData->objective ?? null;
        }
        
        $primaryDetails['total_experience'] = $resumeData->totalYearsExperience ?? null;
        $primaryDetails['languages'] = $this->fetchAllLanguages() ?? null;
        $primaryDetails['linkedin_id'] = isset($resumeData->linkedin) ? $resumeData->linkedin : null ?? null;

        if($this->save === true && count($primaryDetails['emails']) > 0){
            $talent = Talent::find($this->talent_id)
            ->update(
                [
                    'objective' => $primaryDetails['objective'],
                    'total_experience' => $primaryDetails['total_experience'],
                    // "contact_number" => isset($primaryDetails['phoneNumbers'][0]) ? $primaryDetails['phoneNumbers'][0] : null,
                    // "linkedin_id" => $primaryDetails['linkedin_id'],
                ]
            );
        }

        return $primaryDetails ?? null;
    }
    //Small Small Functions
    public function fetchAllPhoneNumbers()
    {
        $resumeData = $this->parseData;
        $phones = [];
        if(isset($resumeData->phoneNumbers) && !empty($resumeData->phoneNumbers)){
            foreach ($resumeData->phoneNumbers as $phoneNumber) {
                array_push($phones, $phoneNumber);
            }
        }
        return $phones ?? null;
    }
    public function fetchAllEmails()
    {
        $resumeData = $this->parseData;
        $emails = [];
        if(isset($resumeData->emails) && !empty($resumeData->emails)){
            foreach ($resumeData->emails as $resumeEmail) {
                array_push($emails, $resumeEmail);
            }
        }
        return $emails ?? null;
    }
    public function fetchAllLanguages()
    {
        $resumeData = $this->parseData;
        $languages = [];
        if(isset($resumeData->languages) && !empty($resumeData->languages)){
            foreach ($resumeData->languages as $resumelang) {
                array_push($languages, $resumelang);
            }
        }
        return $languages ?? null;
    }

    //Fetch Experience Data 
    public function fetchExperienceFromResumeData()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->workExperience) && !empty($resumeData->workExperience)){
            $experiences = [];
            foreach($resumeData->workExperience as $resumeExperience){
                $experience = [];
                $experience['job_title'] = $resumeExperience->jobTitle ?? null;
                $experience['organization'] = $resumeExperience->organization ?? null;
                $experience['location'] = $resumeExperience->location ?? null;
                $experience['job_description'] = $resumeExperience->jobDescription ?? null;
                $experience['start_date'] = $resumeExperience->dates->startDate ?? null;
                $experience['end_date'] = $resumeExperience->dates->endDate ?? null;
                $experience['is_current'] = $resumeExperience->dates->isCurrent ?? null;
                $experience['experience_in_month'] = $resumeExperience->dates->monthsInPosition ?? null;

                $experiences[] = $experience;

                if($this->save === true){
                    TalentExperience::updateOrCreate(
                        [
                            'talent_id' => $this->talent_id, 
                            'title' => $experience['job_title'],
                            'company_name' => $experience['organization']
                        ],
                        [
                            'job_description' => $experience['job_description'],
                            'start_date' => $experience['start_date'],
                            'end_date' => $experience['end_date'],
                            'is_current' => $experience['is_current']
                        ]
                    );
                }
            }

            return $experiences ?? null;
        }
    }
    public function fetchEducationDetailsFromResumeData()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->education) && !empty($resumeData->education)){
            $educations = [];
            foreach($resumeData->education as $resumeEducation){
                $education = [];
                $education['organization'] = $resumeEducation->organization ?? null;
                // if(isset($resumeEducation->accreditation) && !empty($resumeEducation->accreditation)){
                    $education['education'] = $resumeEducation->accreditation->education ?? null;
                    $education['educationLevel'] = $resumeEducation->accreditation->educationLevel ?? null;
                // }
                // if(isset($resumeEducation->grade) && !empty($resumeEducation->grade)){
                    $education['percentage'] = $resumeEducation->grade->raw ?? null;
                // }
                // if(isset($resumeEducation->dates) && !empty($resumeEducation->dates)){
                    $education['start_date'] = $resumeEducation->dates->startDate ?? null;
                    $education['end_date'] = $resumeEducation->dates->completionDate ?? null;
                    $education['is_current'] = $resumeEducation->dates->isCurrent ?? null;
                // }
                // if(isset($resumeEducation->location) && !empty($resumeEducation->location)){
                    $education['city'] = $resumeEducation->location->city ?? null;
                    $education['pincode'] = $resumeEducation->location->postalCode ?? null;
                    $education['state'] = $resumeEducation->location->state ?? null;
                    $education['country'] = $resumeEducation->location->country ?? null;
                    $education['full_address'] = $resumeEducation->location->rawInput ?? null;
                // }

                $educations[] = $education;

                if($this->save === true){
                    TalentEducation::updateOrCreate(
                        [
                            'talent_id' => $this->talent_id, 
                            'university' => $education['organization'],
                            'degree' => $education['education']
                        ],
                        [
                            'degree_type' => $education['educationLevel'],
                            'start_date' => $education['start_date'],
                            'end_date' => $education['end_date'],
                            'is_current' => $education['is_current']
                        ]
                    );
                }
            }
            return $educations ?? null;
        }
    }

    //Fetch Skills Data
    public function fetchSkillsFromResumeData()
    {
        $i = 0;
        $resumeData = $this->parseData;
        $dataBaseSkillsData = \App\Models\SkillMaster::pluck('id','name')->toArray();
        if(isset($resumeData->skills) && !empty($resumeData->skills)){
            $skills = [];
            foreach($resumeData->skills as $resumeSkill){
                    $skills[$i]['skill_type'] = $resumeSkill->type ?? null;
                    $skills[$i]['skill_name'] = $resumeSkill->name ?? null;
                    $skills[$i]['skill_experiance'] = $resumeSkill->numberOfMonths ?? null;
                    $i++;

                    if($this->save === true){
                        if(isset($dataBaseSkillsData[$resumeSkill->name])){
                            $findSkillId = $dataBaseSkillsData[$resumeSkill->name];
                        } else {
                            $findSkillId = SkillMaster::updateOrCreate(
                                ['name' => $resumeSkill->name],
                                ['status' => 0]
                            );
                            $findSkillId = $findSkillId->id;
                        }

                        TalentSkill::updateOrCreate(
                            [
                                'talent_id' => $this->talent_id, 
                                'skill_id' => $findSkillId,
                                'skill_type' => 'Primary'
                            ],
                            [
                                'status' => 1
                            ]
                        );
                    }
            }
        }
        return $skills ?? null;
    }

    public function fetchLocatiobDetailsFromResumeData()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->location) && !empty($resumeData->location)){
            $locations = [];
            $locations['city'] = $resumeData->location->city ?? null;
            $locations['pincode'] = $resumeData->location->postalCode ?? null;
            $locations['state'] = $resumeData->location->state ?? null;
            $locations['country'] = $resumeData->location->country ?? null;
            $locations['full_address'] = $resumeData->location->rawInput ?? null;
            return $locations ?? null;
        }
    }

    public function fetchCertificateDetailsFromResumeData()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->certifications) && !empty($resumeData->certifications)){
            return $resumeData->certifications ?? null;
        }
    }

    public function fetchExperienceSkillDetails()
    {
        $resumeData = $this->parseData;
        $dataBaseSkillsData = \App\Models\SkillMaster::pluck('id','name')->toArray();
        if($this->talent_id != 0){
            $dataBaseExperienceData = \App\Models\TalentExperience::where('talent_id', $this->talent_id)->get();
            $i = 0;
            foreach($dataBaseExperienceData as $workExperience){
                foreach ($dataBaseSkillsData as $key => $value) {
                    if(str_contains($workExperience->job_description, $key)) { 
                        $skills[$i]['organization_name'] = $workExperience->company_name ?? null;
                        $skills[$i]['skill_name'] = $key ?? null;
                        $skills[$i]['experience_id'] = $workExperience->id ?? null;
                        $skills[$i]['skill_id'] = $value ?? null;
                        $i++;
                        if($this->save === true){
                            TalentExperienceSkill::updateOrCreate(
                                [
                                    'talent_id' => $this->talent_id, 
                                    'experience_id'=> $workExperience->id,
                                    'skill_id'=> $value
                                ],
                                [
                                    'status' => 1,
                                ]
                            );
                        }
                    }
                }
            }
        } else {
            if(isset($resumeData->workExperience) && !empty($resumeData->workExperience)){
                $i = 0;
                foreach($resumeData->workExperience as $workExperience){
                    foreach ($dataBaseSkillsData as $key => $value) {
                        if(str_contains($workExperience->jobDescription, $key)) { 
                            $skills[$i]['organization_name'] = $workExperience->organization;
                            $skills[$i]['skill_name'] = $key;
                            $i++;
                        }
                    }
                }
            }
        }
        return $skills ?? null;
    }

    // public function fetchSections(){
    //     $resumeData = $this->parseData;
    //     if(isset($resumeData->sections) && count($resumeData->sections) > 0){
    //         foreach($resumeData->sections as $section){
    //             if($section->sectionType == "Achievements"){

    //             }
    //         }
    //     }
    // }
}