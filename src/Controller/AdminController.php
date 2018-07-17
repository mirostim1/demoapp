<?php

namespace App\Controller;

use App\Entity\Category;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\View\TwitterBootstrap3View;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;
use App\Entity\Post;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AdminController extends AbstractController
{

    /**
     * @Route("/admin/profile", name="admin_profile")
     */
    public function adminLogin(Request $request, SessionInterface $session)
    {
        $userId = $this->getUser()->getId();

        $role = $this->getUser()->getRoles();

        if($role[0] == 'ROLE_ADMIN') {
            $isAdmin = 'Yes';
        } else {
            $isAdmin = 'No';
        }

        $repository = $this->getDoctrine()->getRepository(Post::class);

        $posts = $repository->findBy([
            'user_id' => $userId
        ]);

        $nrPosts = count($posts);

        return $this->render('admin/profile.html.twig',
            [
                'controller_name' => 'AdminController',
                'email' => $this->getUser()->getEmail(),
                'is_admin' => $isAdmin,
                'nr_posts' => $nrPosts
            ]
        );
    }

    /**
     * @Route("/admin/allposts", name="admin_posts")
     */
    public function allposts(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $queryBuilder = $em->createQueryBuilder()
                    ->select('p')
                    ->from('App\Entity\Post', 'p')
                    ->orderBy('p.id', 'DESC');

        $currentPage = $request->query->get('page');

        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new DoctrineORMAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);

        if($currentPage > $pagerfanta->getNbPages() || $currentPage < 1) {
            return $this->redirect('/admin/allposts', 302);
        }

        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('admin/allposts.html.twig',
            [
                'controller_name' => 'AdminController',
                'my_pager' => $pagerfanta,
            ]
        );
    }

    /**
     * @Route("/admin/editpost", name="admin_edit_post")
     */
    public function editpost(Request $request)
    {
        $postId = $request->request->get('post_id');

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
            ->add('id', HiddenType::class, array(
                'attr' => ['class' => 'form-control']
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category',
                'attr' => ['class' => 'form-control']
            ))
            ->add('register', SubmitType::class, array(
                'label' => 'Update',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $editPost = $entityManager->getRepository(Post::class)->find($post['id']);
            $editPost->setTitle($post['title']);
            $editPost->setContent($post['content']);
            $editPost->setCategoryId($post['category_id']);
            $date = new \DateTime();
            $editPost->setEditedAt($date);

            if($post['image_path'] != null) {
                $newFileName = 'image' . time();
                $file = $form['image_path']->getData();
                $extension = $file->guessExtension();

                if($file->move('img/posts', $newFileName . '.' . $extension)) {
                    $editPost->setImagePath($newFileName . '.' . $extension);
                }
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Post has been successfully edited');
                return $this->redirectToRoute('admin_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during editing post');
                return $this->redirectToRoute('admin_edit_post');
            }
        }

        return $this->render('admin/editpost.html.twig',
            [
                'controller_name' => 'UserController',
                'form' => $form->createView(),
                'post_id' => $postId
            ]);
    }

    /**
     * @Route("/admin/addnew", name="admin_add_new_post")
     */
    public function addnew(Request $request)
    {
        $userId = $this->getUser()->getId();

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
            ->add('user_id', HiddenType::class, array(
                'data' => $userId
            ))
            ->add('image_path', FileType::class, array(
                'attr' => ['class' => 'form-control']
            ))
            ->add('category_id', ChoiceType::class, array(
                'choices' =>  $categoriesDropdown,
                'label' => 'Select Category *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('register', SubmitType::class, array(
                'label' => 'Save',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $post->setTitle($post->getTitle());
            $post->setContent($post->getContent());
            $post->setCategoryId($post->getCategoryId());
            $date = new \DateTime();
            $post->setCreatedAt($date);
            $post->setEditedAt($date);
            $post->setUserId($userId);

            if($post->getImagePath() != null) {
                $newFileName = 'image' . time();
                $file = $form['image_path']->getData();
                $extension = $file->guessExtension();

                if($file->move('img/posts', $newFileName . '.' . $extension)) {
                    $post->setImagePath($newFileName . '.' . $extension);
                }
            } else {
                $post->setImagePath('');
            }

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

            $entityManager->persist($post);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New post has been successfully submitted');
                return $this->redirectToRoute('admin_posts');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving post. Please try again.');
                return $this->redirectToRoute('admin_add_new_post');
            }
        }

        return $this->render('admin/addnew.html.twig',
            [
                'controller_name' => 'AdminController',
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/admin/deletepost", name="admin_delete_post")
     */
    public function deletePost(Request $request)
    {
        $postId = $request->request->get('post_id');

        $repository = $this->getDoctrine()->getRepository(Post::class);
        $post = $repository->find($postId);

        if($post->getImagePath()) {
            $filename = 'img/posts/' . $post->getImagePath();
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($post);

        try {
            $entityManager->flush();
            $fileSystem = new Filesystem();

            if($filename) {
                $fileSystem->remove($filename);
            }

            $this->addFlash('success', 'Post has been succesfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting post');
        }

        return $this->redirectToRoute('admin_posts');
    }

    /**
     * @Route("/admin/allusers", name="admin_users")
     */
    public function allusers(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $queryBuilder = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\User', 'u')
                ->orderBy('u.id', 'DESC');

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new DoctrineORMAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);

        if($currentPage > $pagerfanta->getNbPages() || $currentPage < 1) {
            return $this->redirect('/admin/allusers', 302);
        }

        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('admin/allusers.html.twig',
            [
                'controller_name' => 'AdminController',
                'my_pager' => $pagerfanta
            ]
        );
    }

    /**
     * @Route("/admin/newuser", name="admin_add_new_user")
     */
    public function addNewUser(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = new User();

        $rolesArray = [
            'User' => 'ROLE_USER',
            'Admin'  => 'ROLE_ADMIN'
        ];

        $form = $this->createFormBuilder($user)
            ->add('roles', ChoiceType::class, array(
                'label' => 'Select Role *',
                'choices' => $rolesArray,
                'attr' => ['class' => 'form-control'],
                'multiple' => true,
            ))
            ->add('username', EmailType::class, array(
                'label' => 'Enter Email *',
                'attr' => ['class' => 'form-control'],
            ))
            ->add('plainPassword', PasswordType::class, array(
                'label' => 'Enter Password *',
                'attr' => ['class' => 'form-control'],
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Submit',
                'attr' => ['class' => 'form-control btn btn-success'],
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();

            $user->setEmail($user->getUsername());
            $user->setPassword($passwordEncoder->encodePassword($user, $user->getPlainPassword()));
            $user->setRoles($user->getRoles());
            $user->setIsActive(1);

            $entityManager->persist($user);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'New user has been successfully created');
                return $this->redirectToRoute('admin_users');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving new user');
                return $this->redirectToRoute('admin_add_new_user');
            }
        }

        return $this->render('admin/newuser.html.twig',
            [
                'controller_name' => 'AdminController',
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/admin/edituser", name="admin_edit_user")
     */
    public function editUser(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(User::class);

        $user = $repository->findOneBy([
            'id' => $request->request->get('user_id')
        ]);

        $rolesArray = [
            'User' => 'ROLE_USER',
            'Admin'  => 'ROLE_ADMIN'
        ];

        if($user) {
            $userId = $user->getId();
        }

        $form = $this->createFormBuilder($user)
            ->add('roles', ChoiceType::class, array(
                'label' => 'Choose Role',
                'choices' => $rolesArray,
                'attr' => ['class' => 'form-control'],
                'multiple' => true,
            ))
            ->add('username', EmailType::class, array(
                'label' => 'Users Email',
                'required' => true,
                'attr' => ['class' => 'form-control', 'readonly' => true],
            ))
            ->add('id', HiddenType::class)
            ->add('submit', SubmitType::class, array(
                'label' => 'Update',
                'attr' => ['class' => 'form-control btn btn-success'],
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            //var_dump($user); die();

            $entityManager = $this->getDoctrine()->getManager();
            $editUser = $entityManager->getRepository(User::class)->find($user['id']);

            $editUser->setEmail($user['username']);
            $editUser->setRoles($user['roles']);
            $editUser->setIsActive(1);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'User has been successfully edited');
                return $this->redirectToRoute('admin_users');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error during editing user');
                return $this->redirectToRoute('admin_add_new_user');
            }
        }

        return $this->render('admin/edituser.html.twig',
            [
                'controller_name' => 'AdminController',
                'form' => $form->createView(),
                'user_id' => $userId
            ]
        );
    }

    /**
     * @Route("/admin/deleteuser", name="admin_delete_user")
     */
    public function deleteUser(Request $request)
    {
        $userId = $request->request->get('user_id');
        
        $repository = $this->getDoctrine()->getRepository(User::class);

        $entityManager = $this->getDoctrine()->getManager();

        $user = $repository->find($userId);

        $entityManager->remove($user);

        try {
            $entityManager->flush();
            $this->addFlash('success', 'User has been successfully deleted');
        } catch(\Exception $e) {
            $this->addFlash('error', 'Error while deleting user');
        }

        return $this->redirectToRoute('admin_users');
    }

    /**
     * @Route("/admin/allmedia", name="admin_media")
     */
    public function allmedia(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Post::class);
        $posts = $repository->findAll();

        $temp = [];
        foreach($posts as $post) {
            if($post->image_path) {
                array_push($temp, $post->image_path);
            }
        }

        $files = scandir('img/posts');

        $imagesInDir = [];
        $flag = 0;
        foreach($files as $key => $image) {
            if($key > 1) {
                foreach($temp as $item) {
                    if($image == $item) {
                        $flag = 1;
                    }
                }
                if($flag == 1) {
                    array_push($imagesInDir, ['path' => $image, 'used' => 1]);
                    $flag = 0;
                } else {
                    array_push($imagesInDir, ['path' => $image, 'used' => 0]);
                }
            }
        }

        $currentPage = $request->query->get('page');
        if(!$currentPage) {
            $currentPage = 1;
        }

        $adapter = new ArrayAdapter($imagesInDir);
        $pagerfanta = new Pagerfanta($adapter);

        if($currentPage > $pagerfanta->getNbPages() || $currentPage < 1) {
            return $this->redirect('/admin/allmedia', 302);
        }

        $pagerfanta->setMaxPerPage(10)->setCurrentPage($currentPage);

        return $this->render('admin/allmedia.html.twig',
            [
                'controller_name' => 'AdminController',
                'my_pager' => $pagerfanta
            ]
        );
    }

    /**
     * @Route("/admin/deleteimage", name="admin_delete_image")
     */
    public function deleteImage(Request $request)
    {
        $image = $request->request->get('image_name');

        $imagePath = 'img/posts/' . $image;

        if(file_exists($imagePath)) {
            $fileSystem = new Filesystem();

            try {
                $repository = $this->getDoctrine()->getRepository(Post::class);
                $entityManager = $this->getDoctrine()->getManager();

                $post = $repository->findOneBy([
                    'image_path' => $image
                ]);

                if($post) {
                    if($post->image_path == $image) {
                        $post->setImagePath('');
                        $entityManager->flush();
                    }
                }

                $fileSystem->remove($imagePath);

                $this->addFlash('success', 'Image has been successfully deleted');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during delete image');
            }
        } else {
            $this->addFlash('error', 'Selected file not exsits');
        }

        return $this->redirectToRoute('admin_media');
    }

    /**
     * @Route("/admin/deleteimages", name="admin_delete_images")
     */
    public function deleteImages(Request $request)
    {
        $images = $request->request->get('checkbox');

        if($images) {
            $repository = $this->getDoctrine()->getRepository(Post::class);
            $entityManager = $this->getDoctrine()->getManager();
            $fileSystem = new Filesystem();

            foreach($images as $image) {
                $imagePath = 'img/posts/' . $image;

                if(file_exists($imagePath)) {
                    try {
                        $post = $repository->findOneBy([
                            'image_path' => $image
                        ]);

                        if($post) {
                            if($post->image_path == $image) {
                                $post->setImagePath('');
                                $entityManager->flush();
                            }
                        }
                        $fileSystem->remove($imagePath);
                        $msg = 'Selected images have been successfully deleted';
                    } catch(\Exception $e) {
                        $this->addFlash('error', 'Error during delete selected images');
                    }
                }
            }

            if($msg) {
                $this->addFlash('success', $msg);
            }
        }

        return $this->redirectToRoute('admin_media');
    }

    /**
     * @Route("/admin/allcategories", name="admin_categories")
     */
    public function categories(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Category::class);
        $categories = $repository->findAll();
        rsort($categories);

        $category = new Category();

        $form = $this->createFormBuilder($category)
            ->add('name', TextType::class, array(
                'label' => 'Name for category *',
                'attr' => ['class' => 'form-control']
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Add',
                'attr' => ['class' => 'form-control btn btn-success']
            ))
            ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();

            $date = new \DateTime();
            $category->setCreatedAt($date);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($category);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Category has been successfully created');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error during saving new category');
            }

            return $this->redirectToRoute('admin_categories');
        }

        return $this->render('admin/allcategories.html.twig',
            [
                'categories' => $categories,
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/admin/deletecategory", name="admin_delete_category")
     */
    public function deleteCategory(Request $request)
    {
        $categoryId = $request->request->get('category_id');

        $repository = $this->getDoctrine()->getRepository(Category::class);
        $category = $repository->findOneBy(
            [
                'id' => $categoryId
            ]
        );

        try {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($category);
            $entityManager->flush();
            $this->addFlash('success', 'Category has been succesfully deleted');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error during delete category');
        }

        return $this->redirectToRoute('admin_categories');
    }
}
