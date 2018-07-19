<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Image;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;
use App\Entity\Post;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


class UserController extends AbstractController
{
    /**
     * @Route("/user/profile", name="user_profile")
     */
    public function profile(Request $request, SessionInterface $session)
    {
        $user = $this->getUser();

        $role = $user->getRoles();

        if($role[0] == 'ROLE_ADMIN') {
            $isAdmin = 'Yes';
        } else {
            $isAdmin = 'No';
        }

        $userId = $user->getId();

        $repository = $this->getDoctrine()->getRepository(Post::class);

        $posts = $repository->findBy([
            'user_id' => $userId,
        ]);

        $nrPosts = count($posts);

        return $this->render('user/profile.html.twig',
            [
                'controller_name' => 'UserController',
                'email' => $user->getEmail(),
                'is_admin' => $isAdmin,
                'nr_posts' => $nrPosts
            ]
        );
    }

    /**
     * @Route("/user/newpass", name="user_new_password")
     */
    public function newPassword(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array(
                    'label' => 'New Password *',
                    'attr' => ['class' => 'form-control']
                ),
                'second_options' => array(
                    'label' => 'Repeat Password *',
                    'attr' => ['class' => 'form-control']
                ),
                'invalid_message' => 'The password fields must match.'
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Submit',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userData = $this->getUser();

            $repository = $this->getDoctrine()->getRepository(User::class);

            $newPass = $repository->findOneBy([
                'id' => $userData->getId()
            ]);

            $entityManager = $this->getDoctrine()->getManager();

            $newPass->setPassword($passwordEncoder->encodePassword($user, $data->getPlainPassword()));

            $entityManager->persist($newPass);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Password has been changed successfully');
                return $this->redirectToRoute('user_profile');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during changing password');
                return $this->redirectToRoute('user_profile');
            }
        }

        return $this->render('user/newpass.html.twig',
            [
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/user/posts", name="user_posts")
     */
    public function posts(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();

        $queryBuilder = $em->createQueryBuilder()
                ->select('p')
                ->from('App\Entity\Post', 'p')
                ->where('p.user_id = :user_id')
                ->orderBy('p.id', 'DESC')
                ->setParameter('user_id', $user->getId());

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new DoctrineORMAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);

        if($currentPage > $pagerfanta->getNbPages() || $currentPage < 1) {
            return $this->redirect('/user/posts', 302);
        }

        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('user/posts.html.twig',
            [
                'my_pager' => $pagerfanta
            ]
        );
    }

    /**
     * @Route("/user/addnew", name="user_add_new_post")
     */
    public function addNewPost(Request $request)
    {
        $user = $this->getUser();
        $userId = $user->getId();

        $repository = $this->getDoctrine()->getRepository(Category::class);

        $categories = $repository->findAll();

        $categoriesDropdown = [];
        foreach($categories as $category) {
            $categoriesDropdown[$category->getName()] = $category->getId();
        }

        $post = new Post();

        $form = $this->createFormBuilder($post)
            ->add('title', TextType::class, array(
                'label' => 'Enter Title *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('content', TextareaType::class, array(
                'label' => 'Enter Content of Post *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('image_path', FileType::class, array(
                'attr' => ['class' => 'form-control']
            ))
            ->add('user_id', HiddenType::class, array(
                'data' => $userId
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('register', SubmitType::class, array(
                'label' => 'Submit',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $user = $this->getUser();
            $userId = $user->getId();

            $entityManager = $this->getDoctrine()->getManager();

            $post->setTitle($post->getTitle());
            $post->setContent($post->getContent());
            $date = new \DateTime();
            $post->setCreatedAt($date);
            $post->setEditedAt($date);

            $repoCategory = $this->getDoctrine()->getRepository(Category::class);
            $repoUser = $this->getDoctrine()->getRepository(User::class);

            $categoryData = $repoCategory->findOneBy(
                [
                    'id' => $post->getCategoryId()
                ]
            );

            $userData = $repoUser->findOneBy(
                [
                    'id' => $userId
                ]
            );

            $post->setCategory($categoryData);
            $post->setUser($userData);

            if($post->getImagePath() != null) {
                $newFileName = 'image' . time();
                $file = $form['image_path']->getData();
                $extension = $file->guessExtension();

                if($file->move('img/posts', $newFileName . '.' . $extension)) {
                    $image = new Image();
                    $image->setImagePath($newFileName. '.' .$extension);
                    $entityManager->persist($image);

                    try {
                        $entityManager->flush();

                        $qb = $entityManager->createQueryBuilder()
                            ->select('i')
                            ->from('App\Entity\Image', 'i')
                            ->setMaxResults(1)
                            ->orderBy('i.id', 'DESC');

                        $lastImage = $qb->getQuery()->getSingleResult();

                        $post->setImageId($lastImage->getId());
                        $post->setImagePath($newFileName. '.' .$extension);
                        $post->setImage($lastImage);
                    } catch (\Exception $e) {
                        echo $e;
                    }
                }
            }

            $entityManager->persist($post);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New post has been successfully added');
                return $this->redirectToRoute('user_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving');
                return $this->redirectToRoute('user_add_new_post');
            }
        }

        return $this->render('user/addnew.html.twig',
            [
                'form' => $form->createView()
            ]);
    }

    /**
     * @Route("/user/editpost", name="user_edit_post")
     */
    public function editPost(Request $request)
    {
        $postId = $request->request->get('post_id');

        $user = $this->getUser();
        $userId = $user->getId();

        $repository = $this->getDoctrine()->getRepository(Category::class);

        $categories = $repository->findAll();

        $categoriesDropdown = [];
        foreach($categories as $category) {
            $categoriesDropdown[$category->getName()] = $category->getId();
        }

        $repository = $this->getDoctrine()->getRepository(Post::class);

        $post = $repository->findOneBy([
            'id' => $postId
        ]);

        $form = $this->createFormBuilder($post)
            ->add('title', TextType::class, array(
                'label' => 'Enter Title *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('content', TextareaType::class, array(
                'label' => 'Enter Content of Post *',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ))
            ->add('image_path', FileType::class, array(
                'attr' => ['class' => 'form-control'],
                'data_class' => null
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category',
                'attr' => ['class' => 'form-control']
            ))
            ->add('id', HiddenType::class, array())
            ->add('register', SubmitType::class, array(
                'label' => 'Update',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $post = $entityManager->getRepository(Post::class)
                    ->find($formData['id']);

            $post->setTitle($formData['title']);
            $post->setContent($formData['content']);
            $post->setCategoryId($formData['category_id']);
            $date = new \DateTime();
            $post->setEditedAt($date);
            $post->setUserId($userId);

            if($formData['image_path'] != null) {
                $newFileName = 'image' . time();
                $file = $formData['image_path'];
                $extension = $file->guessExtension();

                if($file->move('img/posts', $newFileName . '.' . $extension)) {
                    $image = $post->getImage();

                    if(!$image) {
                        $image = new Image();
                        $em = $this->getDoctrine()->getManager();
                        $image->setImagePath($newFileName . '.' . $extension);
                        $em->persist($image);
                        $em->flush();

                        $qb = $entityManager->createQueryBuilder()
                            ->select('i')
                            ->from('App\Entity\Image', 'i')
                            ->setMaxResults(1)
                            ->orderBy('i.id', 'DESC');

                        $lastImage = $qb->getQuery()->getSingleResult();
                        $post->setImageId($lastImage->getId());
                    }

                    $image->setImagePath($newFileName . '.' . $extension);
                }
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Post has been successfully edited');
                return $this->redirectToRoute('user_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during editing post');
                return $this->redirectToRoute('user_edit_post');
            }
        }

        return $this->render('user/editpost.html.twig',
            [
                'controller_name' => 'UserController',
                'form' => $form->createView(),
                'post_id' => $postId
            ]);
    }

    /**
     * @Route("/user/deletepost", name="user_delete_post")
     */
    public function deletePost(Request $request)
    {
        $postId = $request->request->get('post_id');

        $em = $this->getDoctrine()->getManager();

        $qb = $em->createQueryBuilder()
            ->delete('App\Entity\Post', 'p')
            ->where('p.id = :post_id')
            ->setParameter('post_id', $postId)
            ->getQuery();

        try {
            $qb->getResult();
            $this->addFlash('success', 'Post has been successfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting post');
        }

        return $this->redirectToRoute('user_posts');
    }
}