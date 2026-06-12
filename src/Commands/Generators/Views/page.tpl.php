<@php 
/**
 * @var $this \CodeIgniter\View\View 
 */ 
?>
{comments}
<@php $this->extend('{layout}') ?>

<@php $this->section('content') ?>
    <div>
        <h1>This is the {page} page</h1>
    </div>
<@php $this->endSection() ?>