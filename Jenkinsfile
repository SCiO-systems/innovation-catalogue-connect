def subject = "${env.JOB_NAME} - Build #${env.BUILD_NUMBER}"
def content = '${JELLY_SCRIPT,template="html"}'
def stage_tag = 'dev'
def project_name = 'innovation-catalogue-connect'
def deployment_instance = 'innovation.dev.api.scio.services'

pipeline {
    agent any

    stages {
        stage('Fetching repository from BitBucket') {
            steps {
                echo 'Fetching repository from BitBucket'

                script {
                    checkout scm
                    sh 'git rev-parse --short HEAD > .git/commit-id'
                    commit_id = readFile('.git/commit-id').trim()
                }
            }
        }

        stage('Building & pushing docker images to DockerHub') {
            steps {
                echo 'Building project'

                script {
                    sh "cp /envs/laravel/${project_name}/${stage_tag}.env .env"

                    docker.withRegistry('https://index.docker.io/v1/', 'DockerHub') {
                        docker.build("sciohub/${project_name}:php-${stage_tag}", ".").push()
                    }
                }
            }
        }

        // stage('Checking code on Sonarqube') {
        //     steps {
        //         withSonarQubeEnv('sonarqube') {
        //             sh 'mvn sonar:sonar'
        //         }
        //     }
        // }

        stage('Deployment stage') {
            steps {
                echo "Deploying to ${stage_name} environment"

                script {
                    sshagent(credentials: ['jenkins-ssh-key']) {
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} sudo mkdir -p /var/lib/${project_name}-${stage_tag}"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} sudo chown -R scio:scio /var/lib/${project_name}-${stage_tag}"
                        sh "scp -P 1412 -o StrictHostKeyChecking=no docker-compose.${stage_tag}.yml scio@${deployment_instance}:/var/lib/${project_name}-${stage_tag}"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker rm -f laravel-app nginx-laravel"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker-compose -f /var/lib/${project_name}-${stage_tag}/docker-compose.${stage_tag}.yml up -d"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker exec -it laravel-app chown -R www-data:www-data ."
                        
                        // sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker-compose -f /var/lib/${project_name}-${stage_tag}/docker-compose.${stage_tag}.yml run --rm -u root composer update"
                    // sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker-compose -f /var/lib/${project_name}-${stage_tag}/docker-compose.${stage_tag}.yml run --rm -u root artisan migrate --seed"
                    }
                }
            }
        }
    }

    // Cleaning Jenkins workspace
    post {
        always {
            sh 'docker image prune -a -f' // remove built images
            emailext(body: content, mimeType: 'text/html',
        replyTo: '$DEFAULT_REPLYTO', subject: subject,
        to: 'dev@scio.systems', attachLog: true )
            cleanWs()
        }
    }
}
