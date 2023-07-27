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

    public function __construct()
    {
        $this->parseResume();
    }

    public function parseResume()
    {
        $filePath = "https://www.saurabhshukla.in/saurabh-resume.pdf";
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
            CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath)),
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
        if($resumeData->objective == '' && $resumeData->summary != '') {
            $primaryDetails['objective'] = $resumeData->summary ?? null;
        } else {
            $primaryDetails['objective'] = $resumeData->objective ?? null;
        }

        $primaryDetails['total_experience'] = $resumeData->totalYearsExperience ?? null;
        $primaryDetails['languages'] = $this->fetchAllLanguages() ?? null;
        $primaryDetails['linkedin_id'] = isset($resumeData->linkedin) ? $resumeData->linkedin : null ?? null;

        return $primaryDetails ?? null;
    }
    //Small Small Functions
    public function fetchAllPhoneNumbers()
    {
        $resumeData = $this->parseData;
        $phones = [];
        if(isset($resumeData->phoneNumbers) && !empty($resumeData->phoneNumbers)) {
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
        if(isset($resumeData->emails) && !empty($resumeData->emails)) {
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
        if(isset($resumeData->languages) && !empty($resumeData->languages)) {
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
        if(isset($resumeData->workExperience) && !empty($resumeData->workExperience)) {
            $experiences = [];
            foreach($resumeData->workExperience as $resumeExperience) {
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
            }

            return $experiences ?? null;
        }
    }
    public function fetchEducationDetailsFromResumeData()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->education) && !empty($resumeData->education)) {
            $educations = [];
            foreach($resumeData->education as $resumeEducation) {
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
            }
            return $educations ?? null;
        }
    }

    //Fetch Skills Data
    public function fetchSkillsFromResumeData()
    {
        $i = 0;
        $resumeData = $this->parseData;
        if(isset($resumeData->skills) && !empty($resumeData->skills)) {
            $skills = [];
            foreach($resumeData->skills as $resumeSkill) {
                $skills[$i]['skill_type'] = $resumeSkill->type ?? null;
                $skills[$i]['skill_name'] = $resumeSkill->name ?? null;
                $skills[$i]['skill_experiance'] = $resumeSkill->numberOfMonths ?? null;
                $i++;
            }
        }
        return $skills ?? null;
    }

    public function fetchLocatiobDetailsFromResumeData()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->location) && !empty($resumeData->location)) {
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
        if(isset($resumeData->certifications) && !empty($resumeData->certifications)) {
            return $resumeData->certifications ?? null;
        }
    }

    public function fetchExperienceSkillDetails()
    {
        $resumeData = $this->parseData;
        if(isset($resumeData->workExperience) && !empty($resumeData->workExperience)) {
            $i = 0;
            foreach($resumeData->workExperience as $workExperience) {
                $skills[$i]['organization_name'] = $workExperience->organization;
                $skills[$i]['skill_name'] = $workExperience->jobDescription;
                $i++;
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
