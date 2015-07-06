<?php

namespace Devblock\CourseGrabBundle\Services;

use Doctrine\ORM\EntityManager;
use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\DomCrawler\Crawler;
use Devblock\CourseGrabBundle\Entity\Course;

class EllucianScrapper {
    /** @var $em Doctrine\ORM\EntityManager */
    protected $em;

    /** @var $client Goutte\Client */
    protected $client;
    
    //starting at 0
    const COURSE_TABLE_KEYS = array(
        2  => 'course_number',
        3  => 'subject',
        4  => 'subject_number',
        5  => 'section',
        6  => 'campus',
        7  => 'credits',
        8  => 'title',
        9 => 'days',
        10 => 'time',
        11 => 'capacity',
        12 => 'attending',
        18 => 'instructor',
        19 => 'date',
        20 => 'location',
    );
    
    const COURSE_TABLE_COLUMNS = 21;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->client = new Client();
        //Accept all ssl no matter what
        $this->client->setClient(new GuzzleClient([ 'verify' => false]));
    }

    public function fetchSemesters($url) {
        $semesters = array();
        $crawler = $this->client->request('GET', $url);
        
        $options = $crawler->filter('#term_input_id')->children();
        foreach ($options as $option) {
            $text = strtolower(trim($option->nodeValue));
            //var_dump($text);
            //var_dump(strstr($text, '(view only)'));
            if ($text !== 'none' && !strstr($text, '(view only)')) {
                $id = $option->getAttribute('value');
                array_push($semesters, $id);
            }
        }
        
        return $semesters;
    }

    public function fetchCourses($url, $semesters) {
        $courses = array();
        {
            $semester = $semesters[0];
        //foreach ($semesters as $semester) {
            $params = array(
                'term_in'       => $semester,
                'sel_day'       => 'dummy',
                'sel_schd'      => 'dummy',
                'sel_insm'      => 'dummy',
                'sel_camp'      => 'dummy',
                'sel_levl'      => 'dummy',
                'sel_sess'      => 'dummy',
                'sel_crse'      => '',
                'sel_title'     => '',
                'sel_from_cred' => '',
                'sel_to_cred'   => '',
                'sel_subj'      => array('dummy', 'ACCT'),// '%'),
                'sel_ptrm'      => array('dummy', '%'),
                'sel_loc'       => array('dummy', '%'),
                'sel_instr'     => array('dummy', '%'),
                'sel_attr'      => array('dummy', '%'),
                'begin_hh'      => '0',
                'begin_mi'      => '0',
                'begin_ap'      => 'a',
                'end_hh'        => '0',
                'end_mi'        => '0',
                'end_ap'        => 'a',
            );

            $crawler = new Crawler($this->getPostPage($url, $params));
            
            $tempCourses = $this->parseCourses($crawler);
            
            $courses = array_merge($courses, $tempCourses);
        }

        return $courses;
    }
    
    public function parseCourses(Crawler $crawler) {
        $courses = array();
        $courseTable = $crawler->filter('.datadisplaytable')->first();
        
        $courses = $courseTable->filter('tr')->each(function(Crawler $node, $i) {
            //var_dump($node);
            $course = null;
            
            $columns = $node->filter('td');
            $count = count($columns);
            if ($count == self::COURSE_TABLE_COLUMNS) {
                $course = new Course();
                $i=0;
                foreach ($columns as $col) {
                    $tableKeys = self::COURSE_TABLE_KEYS;
                    if (array_key_exists($i, $tableKeys)) {
                        $text = $col->nodeValue;
                        switch ($tableKeys[$i]) {
                            case 'course_number':
                                $course->setCourseNumber($text);
                                break;
                            case 'subject':
                                $course->setSubject($text);
                                break;
                            case 'subject_number':
                                $course->setSubjectNumber($text);
                                break;
                            case 'section':
                                $course->setSection($text);
                                break;
                            case 'campus':
                                $course->setCampus($text);
                                break;
                            case 'credits':
                                $course->setCredits($text);
                                break;
                            case 'title':
                                $course->setTitle($text);
                                break;
                            case 'days':
                                $course->setDays($text);
                                break;
                            case 'time':
                                
                                break;
                            case 'capacity':
                                $course->setCapacity($text);
                                break;
                            case 'attending':
                                $course->setAttending($text);
                                break;
                            case 'instructor':
                                $course->setInstructor($text);
                                break;
                            case 'date':
                                
                                break;
                            case 'location':
                                $course->setLocation($text);
                                break;
                            
                            default:
                                
                        }
                    }
                    $i++;
                }
            }
            
            return $course;
        });
        
        //remove any null values
        return array_filter($courses);
    }
    
    public function getPostPage($url, $data) {
        //http://stackoverflow.com/questions/8170306/http-build-query-with-same-name-parameters
        $query = http_build_query($data, null);
        $query = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $query);
        
        //var_dump($query);
        
        //open connection
        $ch = curl_init($url);

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        
        //execute post
        $result = curl_exec($ch);
        
        //close connection
        curl_close($ch);
        
        return $result;
    }

}
