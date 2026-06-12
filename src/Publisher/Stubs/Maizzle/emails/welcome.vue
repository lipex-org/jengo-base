<script setup>
  defineConfig({
    user: 'Explorer',
    loginUrl: 'https://example.com/login',
  })
</script>

<template>
  <Layout>
    <Container class="max-w-xl bg-slate-50 p-10 rounded-2xl mt-10">
      <div class="text-center mb-10">
        <Img src="logo.png" class="mx-auto h-16" alt="Logo" />
      </div>
      
      <Heading class="text-3xl font-extrabold text-slate-900 text-center mb-6">
        Welcome to the Family!
      </Heading>
      
      <Text class="text-slate-600 text-lg text-center leading-loose mb-10">
        Hey {{ user }}, we're absolutely thrilled to have you here. Your journey with Jengo starts now.
      </Text>
      
      <div class="text-center">
        <Button :href="loginUrl" class="bg-indigo-600 text-white font-bold py-4 px-10 rounded-full shadow-lg hover:bg-indigo-700">
          Get Started
        </Button>
      </div>

      <div class="mt-12 p-6 bg-white rounded-xl border border-slate-200">
        <Text class="font-bold text-slate-800 mb-2">Need help?</Text>
        <Text class="text-slate-500 text-sm">Our team is always here for you. Just reply to this email.</Text>
      </div>
    </Container>
  </Layout>
</template>
